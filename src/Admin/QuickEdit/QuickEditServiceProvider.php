<?php

declare(strict_types=1);

namespace FieldForge\Admin\QuickEdit;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Fields\FieldDefinition;
use FieldForge\Registry\ContextRegistry;

/**
 * Registers Quick Edit and Bulk Edit UI and save hooks.
 *
 * Quick Edit inputs use the 'ff_qe_{key}' name prefix to avoid collisions.
 * On save_post, these values are merged into a synthetic fieldforge_payload
 * and passed through the existing SavePipeline (sans nonce — WP provides
 * the inline edit nonce via _inline_edit).
 *
 * Excluded from PHPStan — references WP functions.
 */
final class QuickEditServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('quick_edit_custom_box', [$this, 'renderQuickEditFields'], 10, 2);
        add_action('bulk_edit_custom_box',  [$this, 'renderBulkEditFields'],  10, 2);
        add_action('save_post',             [$this, 'saveQuickEditFields']);
        add_action('wp_ajax_fieldforge_bulk_edit', [$this, 'saveBulkEditFields']);
    }

    public function renderQuickEditFields(string $columnName, string $postType): void
    {
        if (! str_starts_with($columnName, 'ff_')) {
            return;
        }

        $fields = $this->getQuickEditFields($postType);
        if (empty($fields)) {
            return;
        }

        (new QuickEditRenderer())->render($fields);
    }

    public function renderBulkEditFields(string $columnName, string $postType): void
    {
        if (! str_starts_with($columnName, 'ff_')) {
            return;
        }

        $fields = $this->getBulkEditFields($postType);
        if (empty($fields)) {
            return;
        }

        (new QuickEditRenderer())->render($fields);
    }

    public function saveQuickEditFields(int $postId): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        // Quick Edit posts include _inline_edit nonce; regular saves do not.
        if (! isset($_POST['_inline_edit'])) {
            return;
        }

        // Verify WordPress's inline-edit nonce before touching any field data.
        if (! check_admin_referer('inlineeditnonce', '_inline_edit')) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = $this->extractQuickEditPayload();
        if (empty($fields)) {
            return;
        }

        $jsonPayload = (string) wp_json_encode($fields);

        $postType = get_post_type($postId);
        if (! is_string($postType)) {
            return;
        }

        // Pass the FieldForge nonce from POST so NonceValidationStage can verify it.
        $rawPost = [
            'fieldforge_payload' => $jsonPayload,
            '_fieldforge_nonce'  => $_POST['_fieldforge_nonce'] ?? '',
        ];

        SavePipeline::run($postId, $rawPost, contextOverride: new AdminContext(postType: $postType));
    }

    public function saveBulkEditFields(): void
    {
        check_ajax_referer('fieldforge_bulk_edit', 'nonce');

        $postIds = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
            ? array_map('absint', $_POST['post_ids'])
            : [];

        if (empty($postIds)) {
            wp_send_json_error(['message' => 'No post IDs provided.']);
            return;
        }

        $fields = $this->extractQuickEditPayload();
        if (empty($fields)) {
            wp_send_json_success(['updated' => 0]);
            return;
        }

        $jsonPayload = (string) wp_json_encode($fields);
        $updated     = 0;

        foreach ($postIds as $postId) {
            $postType = get_post_type($postId);
            if (! is_string($postType) || ! current_user_can('edit_post', $postId)) {
                continue;
            }

            $rawPost = ['fieldforge_payload' => $jsonPayload];
            SavePipeline::run($postId, $rawPost, contextOverride: new AdminContext(postType: $postType));
            $updated++;
        }

        wp_send_json_success(['updated' => $updated]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Collects all fields with ->quickEdit() for the given post type.
     *
     * @return FieldDefinition[]
     */
    private function getQuickEditFields(string $postType): array
    {
        $context = new AdminContext(postType: $postType);
        $groups  = ContextRegistry::resolve($context);
        $fields  = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->isQuickEdit()) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Collects all fields with ->bulkEdit() for the given post type.
     *
     * @return FieldDefinition[]
     */
    private function getBulkEditFields(string $postType): array
    {
        $context = new AdminContext(postType: $postType);
        $groups  = ContextRegistry::resolve($context);
        $fields  = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->isBulkEdit()) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Extracts 'ff_qe_*' values from $_POST into a plain key→value map.
     *
     * @return array<string, mixed>
     */
    private function extractQuickEditPayload(): array
    {
        $fields = [];

        foreach ($_POST as $name => $value) {
            if (! str_starts_with((string) $name, 'ff_qe_')) {
                continue;
            }

            $key = substr((string) $name, 6); // strip 'ff_qe_'
            if ($key === '') {
                continue;
            }

            $fields[$key] = is_array($value)
                ? array_map('sanitize_text_field', $value)
                : sanitize_text_field((string) $value);
        }

        return $fields;
    }
}
