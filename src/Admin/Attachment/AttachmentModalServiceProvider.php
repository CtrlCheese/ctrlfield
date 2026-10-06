<?php

declare(strict_types=1);

namespace CtrlField\Admin\Attachment;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Data\FieldDataService;
use CtrlField\Enums\FieldType;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

/**
 * Renders and saves CtrlField fields inside the WordPress Media Library modal
 * (attachment_fields_to_edit / attachment_fields_to_save).
 *
 * Fields appear when any FieldGroup is registered with:
 *   ->where('post_type', '==', 'attachment')
 *
 * Full edit screen (wp-admin/post.php?post={id}&action=edit) already works via
 * the standard MetaBoxServiceProvider — this provider covers the AJAX modal only.
 *
 * Excluded from PHPStan — references WP functions.
 */
final class AttachmentModalServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        if (! $this->hasAttachmentGroups()) {
            return;
        }

        add_filter('attachment_fields_to_edit', [$this, 'renderModalFields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'saveModalFields'],   10, 2);
    }

    /**
     * @param array<string, mixed> $formFields
     * @return array<string, mixed>
     */
    public function renderModalFields(array $formFields, \WP_Post $post): array
    {
        $context = new AdminContext(postType: 'attachment');
        $groups  = ContextRegistry::resolve($context);

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $key   = $field->getKey();
                $def   = $field->getDefinition();
                $label = $def['label'] ?: $key;
                $value = FieldDataService::getInstance()->get($key, $post->ID, 'post');

                $formFields['ctrlf_' . $key] = [
                    'label' => esc_html($label),
                    'input' => 'html',
                    'html'  => sprintf(
                        '<input type="text" id="attachments-%1$d-ctrlf_%2$s" name="attachments[%1$d][ctrlf_%2$s]" value="%3$s" class="text" style="width:100%%">',
                        $post->ID,
                        esc_attr($key),
                        esc_attr((string) $value),
                    ),
                ];
            }
        }

        return $formFields;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    public function saveModalFields(array $post, array $attachment): array
    {
        $postId = (int) ($post['ID'] ?? 0);
        if ($postId <= 0) {
            return $post;
        }

        if (! function_exists('current_user_can') || ! current_user_can('edit_post', $postId)) {
            return $post;
        }

        $fieldMap = $this->buildFieldTypeMap();
        $fields   = [];

        foreach ($attachment as $name => $value) {
            if (! str_starts_with((string) $name, 'ctrlf_')) {
                continue;
            }

            $key = substr((string) $name, 3);
            if ($key === '') {
                continue;
            }

            $type     = $fieldMap[$key] ?? FieldType::TEXT;
            $fields[$key] = is_array($value)
                ? array_map(fn ($v) => $this->sanitizeByType($type, (string) $v), $value)
                : $this->sanitizeByType($type, (string) $value);
        }

        if (empty($fields)) {
            return $post;
        }

        $existing = FieldDataService::getInstance()->getAll($postId, 'post');
        $merged   = array_merge($existing, $fields);

        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $adapter->save($postId, $merged, SchemaVersion::CURRENT);

        return $post;
    }

    private function hasAttachmentGroups(): bool
    {
        $context = new AdminContext(postType: 'attachment');
        return ! empty(ContextRegistry::resolve($context));
    }

    /** @return array<string, FieldType> */
    private function buildFieldTypeMap(): array
    {
        $context = new AdminContext(postType: 'attachment');
        $map     = [];
        foreach (ContextRegistry::resolve($context) as $group) {
            foreach ($group->getFields() as $field) {
                $map[$field->getKey()] = $field->getType();
            }
        }
        return $map;
    }

    private function sanitizeByType(FieldType $type, string $value): mixed
    {
        return match ($type) {
            FieldType::EMAIL    => sanitize_email($value),
            FieldType::URL,
            FieldType::OEMBED   => esc_url_raw($value),
            FieldType::NUMBER,
            FieldType::RANGE    => is_numeric($value) ? (float) $value : 0.0,
            FieldType::IMAGE,
            FieldType::FILE     => abs((int) $value),
            FieldType::DATE,
            FieldType::TIME,
            FieldType::DATETIME => sanitize_text_field($value),
            FieldType::WYSIWYG  => wp_kses_post($value),
            FieldType::TEXTAREA => sanitize_textarea_field($value),
            default             => sanitize_text_field($value),
        };
    }
}
