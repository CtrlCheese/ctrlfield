<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Data\CsvExporter;
use CtrlField\Registry\FieldRegistry;

/**
 * Schema Inspector actions (admin-post.php): export a group's posts as CSV,
 * migrate payloads to the current schema version, remove left-over values.
 * Each checks the capability and its nonce; migrate and clean up run as a
 * dry run first and report back on the Health tab.
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- each action calls guard() (capability + check_admin_referer) before reading $_GET
final class InspectorActions
{
    public const CAP = 'manage_options';

    public function register(): void
    {
        add_action('admin_post_ctrlfield_export_csv', [$this, 'exportCsv']);
        add_action('admin_post_ctrlfield_inspector_migrate', [$this, 'migrate']);
        add_action('admin_post_ctrlfield_inspector_clean', [$this, 'clean']);
    }

    /** @param array<string, string> $args */
    public static function url(string $action, array $args = []): string
    {
        return wp_nonce_url(add_query_arg(['action' => $action] + $args, admin_url('admin-post.php')), $action);
    }

    public function exportCsv(): void
    {
        $this->guard('ctrlfield_export_csv');
        $groupKey = isset($_GET['group']) ? sanitize_key(wp_unslash((string) $_GET['group'])) : '';
        $group    = FieldRegistry::all()[$groupKey] ?? null;
        $postType = $group !== null ? (SchemaAnalyzer::postTypes($group)[0] ?? null) : null;
        if ($postType === null) {
            wp_die(esc_html__('This group is not shown on a post type, so it has no posts to export.', 'ctrlfield'), 400);
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($postType . '-' . $groupKey . '.csv') . '"');
        foreach ((new CsvExporter())->export($postType) as $row) {
            echo $row; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV download
        }
        exit;
    }

    public function migrate(): void
    {
        $this->guard('ctrlfield_inspector_migrate');
        $dry    = empty($_GET['apply']);
        $result = (new InspectorData())->migrate($dry);
        $this->back(['ctrlf_done' => 'migrate', 'ctrlf_dry' => $dry ? 1 : 0, 'ctrlf_n' => $result['done'], 'ctrlf_failed' => $result['failed']]);
    }

    public function clean(): void
    {
        $this->guard('ctrlfield_inspector_clean');
        $type = isset($_GET['post_type']) ? sanitize_key(wp_unslash((string) $_GET['post_type'])) : '';
        $keys = isset($_GET['keys']) ? array_values(array_filter(array_map('sanitize_key', explode(',', wp_unslash((string) $_GET['keys']))))) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_key on each key
        $dry  = empty($_GET['apply']);
        $n    = $type !== '' && $keys !== [] ? (new InspectorData())->cleanOrphans($type, $keys, $dry) : 0;
        $this->back(['ctrlf_done' => 'clean', 'ctrlf_dry' => $dry ? 1 : 0, 'ctrlf_n' => $n, 'ctrlf_type' => $type, 'ctrlf_keys' => implode(',', $keys)]);
    }

    private function guard(string $action): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer($action);
    }

    /** @param array<string, int|string> $args */
    private function back(array $args): void
    {
        wp_safe_redirect(add_query_arg(['page' => 'ctrlfield', 'tab' => 'health'] + $args, admin_url('admin.php')));
        exit;
    }
}
