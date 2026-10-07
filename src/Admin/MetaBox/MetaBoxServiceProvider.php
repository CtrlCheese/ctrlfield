<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Core\Notifications\NotificationDispatcher;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Registry\FieldRegistry;
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

        // Block editor: meta boxes save in a background request, so the editor
        // asks for the validation error right after (assets/admin/src/index.js).
        add_action('rest_api_init', static function (): void {
            register_rest_route('ctrlfield/v1', '/save-error/(?P<id>\\d+)', [
                'methods'             => 'GET',
                'permission_callback' => static fn (\WP_REST_Request $r): bool => current_user_can('edit_post', (int) $r['id']),
                'callback'            => static function (\WP_REST_Request $r): \WP_REST_Response {
                    $key   = self::saveErrorKey((int) $r['id']);
                    $error = get_transient($key);
                    delete_transient($key);
                    return new \WP_REST_Response(['error' => is_string($error) ? $error : '']);
                },
            ]);
        });

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
            'saveError'   => $this->takeSaveError(),
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

        // Each meta box emits ctrlfield_payload[group_key] = JSON (legacy: one JSON).
        // Merge them and run the pipeline ONCE: per-group runs validated every
        // group against one group's values ("required" failed on the other
        // boxes) and each run overwrote the data saved by the previous one.
        $merged = [];
        foreach ($this->extractPayloads($_POST) as $jsonPayload) {
            $decoded = json_decode(wp_unslash($jsonPayload), true);
            if (is_array($decoded)) {
                $merged = array_merge($merged, $decoded);
            }
        }

        try {
            SavePipeline::run($postId, array_merge($_POST, [
                'ctrlfield_payload' => wp_slash((string) wp_json_encode($merged)),
            ]));
        } catch (PipelineException $e) {
            // A validation error must not turn the save into a 500: keep the
            // message and show it on the next load of the edit screen.
            set_transient(self::saveErrorKey($postId), self::friendlyError($e), 10 * MINUTE_IN_SECONDS);
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

        $groups = array_values(ContextRegistry::resolve(AdminContext::forPost($postId)));
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
    /** Message for editors: the field's label instead of its internal key. */
    private static function friendlyError(PipelineException $e): string
    {
        if ($e->fieldKey === null) {
            return $e->getMessage();
        }

        $label = $e->fieldKey;
        foreach (FieldRegistry::all() as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getKey() === $e->fieldKey) {
                    $label = $field->getDefinition()['label'] ?: $e->fieldKey;
                    break 2;
                }
            }
        }

        if ($e->errorCode === 'REQUIRED_FIELD') {
            /* translators: %s: field label */
            return sprintf(__('"%s" is required. The other fields were not saved either.', 'ctrlfield'), $label);
        }

        /* translators: 1: field label, 2: technical error message */
        return sprintf(__('"%1$s" could not be saved: %2$s', 'ctrlfield'), $label, $e->getMessage());
    }

    public static function saveErrorKey(int $postId): string
    {
        return 'ctrlfield_save_error_' . get_current_user_id() . '_' . $postId;
    }

    /** Error from the last save of this post (pipeline validation), once. */
    private function takeSaveError(): string
    {
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        // Block editor: meta boxes save in a background request whose redirect
        // loads this screen invisibly; only the save-error REST route may
        // consume the message there. Classic editor: show it on this load.
        if ($postId <= 0 || isset($_GET['meta-box-loader']) || use_block_editor_for_post($postId)) {
            return '';
        }
        $key   = self::saveErrorKey($postId);
        $error = get_transient($key);
        delete_transient($key);

        return is_string($error) ? $error : '';
    }

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
