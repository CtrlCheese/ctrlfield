<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Core\Notifications\NotificationDispatcher;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Data\FieldDataService;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

/**
 * Boots the meta box UI and the save_post pipeline hook.
 *
 * Excluded from PHPStan — references WP functions.
 */
class MetaBoxServiceProvider extends ServiceProvider
{
    /**
     * Stores pre-save field values keyed by post ID for notification comparison.
     * Populated in onSavePost before the pipeline runs, consumed in dispatchNotifications.
     * Using a property instead of an inline add_action closure prevents hook accumulation
     * when a save fails mid-pipeline and onSavePost is called again.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $preludeSavedFields = [];

    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // Meta box registration
        $registrar = new MetaBoxRegistrar();
        $registrar->register();

        // Enqueue admin assets on post edit screens
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // save_post pipeline
        add_action('save_post', [$this, 'onSavePost']);

        // Notification dispatcher — registered once, reads from $preludeSavedFields
        add_action('ctrlfield/after_save', [$this, 'dispatchNotifications'], 10, 2);

        // Revision snapshots (X-3)
        add_action('_wp_put_post_revision', [$this, 'onPutRevision']);
        add_action('wp_restore_post_revision', [$this, 'onRestoreRevision'], 10, 2);

        // Oembed live preview for OembedField admin UI
        add_action('wp_ajax_ctrlfield_oembed_preview', [$this, 'onOembedPreview']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $base    = CTRLFIELD_URL . 'assets/admin/';
        $version = CTRLFIELD_VERSION;

        wp_enqueue_style(
            'ctrlfield-admin',
            $base . 'ctrlfield.css',
            [],
            $version,
        );

        wp_enqueue_script(
            'ctrlfield-admin',
            $base . 'ctrlfield.js',
            [],
            $version,
            true, // load in footer
        );

        // Pass attachment URLs and nonces to Alpine
        wp_localize_script('ctrlfield-admin', 'ctrlfieldData', [
            'attachments' => $this->resolveAttachmentUrls(),
            'oembedNonce' => wp_create_nonce('ctrlfield_oembed'),
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'restUrl'     => rest_url(),
            'restNonce'   => wp_create_nonce('wp_rest'),
        ]);

        // WP media library — needed for image/file fields
        wp_enqueue_media();

        // CodeMirror — needed for code editor fields
        wp_enqueue_script('wp-codemirror');
        wp_enqueue_style('wp-codemirror');
    }

    public function onSavePost(int $postId): void
    {
        if (wp_is_post_autosave($postId)) {
            return;
        }

        if (wp_is_post_revision($postId)) {
            return;
        }

        if (! isset($_POST['ctrlfield_payload'])) {
            return;
        }

        // Capture old values before the save for notification comparison (A-12).
        $this->preludeSavedFields[$postId] = FieldDataService::getInstance()->getAll($postId, 'post');

        // Run the pipeline once per submitted group payload.
        // Each meta box emits ctrlfield_payload[group_key] = JSON.
        // Legacy single-payload format (ctrlfield_payload = JSON) is also supported.
        $payloads = $this->extractPayloads($_POST);

        foreach ($payloads as $jsonPayload) {
            SavePipeline::run($postId, array_merge($_POST, ['ctrlfield_payload' => $jsonPayload]));
        }
    }

    public function dispatchNotifications(int $postId, array $newFields): void
    {
        $oldFields = $this->preludeSavedFields[$postId] ?? [];
        unset($this->preludeSavedFields[$postId]);

        $postType = get_post_type($postId);
        if (! is_string($postType)) {
            return;
        }

        $groups = array_values(ContextRegistry::resolve(new AdminContext(postType: $postType)));
        (new NotificationDispatcher())->dispatch($postId, $oldFields, $newFields, $groups);
    }

    public function onPutRevision(int $revisionId): void
    {
        $parentId = (int) wp_get_post_parent_id($revisionId);
        if ($parentId <= 0) {
            return;
        }

        // Use loadRaw() to read fields even if the schema_version is stale.
        // getAll() returns [] for unmigrated posts, which would create an empty snapshot
        // and permanently lose the field history for that revision.
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $raw     = $adapter->loadRaw($parentId);
        $data    = $raw['fields'] ?? [];

        if (empty($data)) {
            return;
        }

        $adapter->save($revisionId, $data, SchemaVersion::CURRENT);
    }

    public function onRestoreRevision(int $postId, int $revisionId): void
    {
        $data = FieldDataService::getInstance()->getAll($revisionId, 'post');
        if (empty($data)) {
            return;
        }

        (new PostMetaAdapter(new WpPostMetaDriver()))->save($postId, $data, SchemaVersion::CURRENT);
    }

    public function onOembedPreview(): void
    {
        check_ajax_referer('ctrlfield_oembed', 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
            return;
        }

        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash((string) $_GET['url'])) : '';

        if ($url === '') {
            wp_send_json_success(['html' => '']);
            return;
        }

        $width = isset($_GET['width']) ? abs((int) $_GET['width']) : 480;
        $html  = wp_oembed_get($url, ['width' => $width]);

        wp_send_json_success(['html' => $html ?: '']);
    }

    /**
     * Resolves thumbnail URLs for any attachment IDs already stored, so Alpine
     * can display existing images without an extra REST call.
     *
     * @return array<int, array{url: string}>
     */
    /**
     * Extracts one JSON string per submitted group from $_POST.
     *
     * New format: ctrlfield_payload[group_key] = JSON string (one per meta box).
     * Legacy format: ctrlfield_payload = JSON string (single meta box, backward compat).
     *
     * @param  array<string, mixed> $post
     * @return list<string>
     */
    private function extractPayloads(array $post): array
    {
        // New per-group array format
        if (isset($post['ctrlfield_payload']) && is_array($post['ctrlfield_payload'])) {
            return array_values(
                array_filter(
                    array_map(static fn($v) => is_string($v) ? $v : null, $post['ctrlfield_payload']),
                )
            );
        }

        // Legacy single-payload format
        if (isset($post['ctrlfield_payload']) && is_string($post['ctrlfield_payload'])) {
            return [$post['ctrlfield_payload']];
        }

        return [];
    }

    private function resolveAttachmentUrls(): array
    {
        // IDs come from the post meta loaded by MetaBoxRenderer.
        // We keep it simple: pass an empty map and let Alpine fetch lazily.
        // Full pre-population can be added without changing the JS contract.
        return [];
    }
}
