<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Data\CsvImporter;
use CtrlField\Registry\FieldRegistry;

final class InspectorServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // Priority 9: the top-level CtrlField menu must exist before any submenu
        // (options pages, Pro pages) is attached to it.
        add_action('admin_menu', static function () {
            (new SchemaInspectorPage())->register();
        }, 9);

        // Map API keys (Google Maps / Mapbox) — the Map field is Free.
        add_action('admin_menu', static fn () => (new \CtrlField\Admin\Map\MapSettingsPage())->register(), 20);

        add_action('wp_ajax_ctrlfield_import_csv', [$this, 'handleCsvImport']);
    }

    public function handleCsvImport(): void
    {
        $group = isset($_POST['group']) ? sanitize_key((string) $_POST['group']) : '';

        if (! check_ajax_referer('ctrlfield_csv_import_' . $group, 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
            return;
        }

        if (! isset($_FILES['csv_file']) || (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'File upload failed.']);
            return;
        }

        $postType = $this->resolvePostTypeForGroup($group);
        if ($postType === null) {
            wp_send_json_error(['message' => 'Group not found or has no post_type context.']);
            return;
        }

        $filePath = (string) ($_FILES['csv_file']['tmp_name'] ?? '');
        if (! is_uploaded_file($filePath)) {
            wp_send_json_error(['message' => 'File upload failed.']);
            return;
        }
        $importer = new CsvImporter();
        $stats    = $importer->import($filePath, $postType, false, true, false);

        wp_send_json_success($stats);
    }

    private function resolvePostTypeForGroup(string $groupKey): ?string
    {
        $groups = FieldRegistry::all();

        if (! isset($groups[$groupKey])) {
            return null;
        }

        foreach ($groups[$groupKey]->getAndConditions() as $condition) {
            if ($condition['key'] === 'post_type' && $condition['operator'] === '==') {
                return (string) $condition['value'];
            }
        }

        return null;
    }
}
