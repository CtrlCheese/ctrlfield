<?php

declare(strict_types=1);

namespace FieldForge\Admin\Inspector;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Data\CsvExporter;
use FieldForge\Data\CsvImporter;
use FieldForge\Registry\FieldRegistry;

/**
 * Renders the Schema Inspector admin page.
 * 100% read-only — no forms, no save actions.
 * Excluded from PHPStan — references WP global $wpdb and WP functions.
 */
final class SchemaInspectorRenderer
{
    public function render(): void
    {
        $groups       = FieldRegistry::all();
        $totalMismatch = 0;

        echo '<div class="wrap">';
        echo '<h1>FieldForge — Schema Inspector</h1>';

        if (empty($groups)) {
            echo '<p>No field groups registered. Define groups in PHP and include them via <code>SchemaLoader</code> or your theme\'s <code>functions.php</code>.</p>';
            echo '</div>';
            return;
        }

        // Gather mismatch data for the global banner
        $groupData = [];
        foreach ($groups as $key => $group) {
            $data = $this->analyzeGroup($group);
            $groupData[$key] = $data;
            $totalMismatch += $data['mismatch_count'];
        }

        // Version mismatch banner
        if ($totalMismatch > 0) {
            printf(
                '<div class="notice notice-warning"><p><strong>⚠ %d post%s with schema_version mismatch detected.</strong> Run <code>wp fieldforge migrate</code> to update.</p></div>',
                $totalMismatch,
                $totalMismatch === 1 ? '' : 's',
            );
        }

        // Field Groups table
        $this->renderCsvAjaxHandler();

        echo '<table class="wp-list-table widefat fixed striped" style="margin-top:16px">';
        echo '<thead><tr>';
        echo '<th>Group Key</th><th>Title</th><th>Context</th><th>Fields</th><th>Coverage</th><th>Version Status</th><th>CSV</th>';
        echo '</tr></thead><tbody>';

        foreach ($groups as $key => $group) {
            $data = $groupData[$key];
            $this->renderGroupRow($key, $group, $data);
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // -------------------------------------------------------------------------

    /** @return array{post_type: ?string, coverage: string, mismatch_count: int, mismatch_display: string} */
    private function analyzeGroup(FieldGroup $group): array
    {
        $postType = null;
        foreach ($group->getAndConditions() as $condition) {
            if ($condition['key'] === 'post_type' && $condition['operator'] === '==') {
                $postType = (string) $condition['value'];
                break;
            }
        }

        $coverage       = '—';
        $mismatchCount  = 0;
        $mismatchDisplay = '✓ Current';

        if ($postType !== null) {
            $coverage = $this->queryCoverage($postType);
            [$mismatchCount, $mismatchDisplay] = $this->queryVersionMismatch($postType);
        } elseif ($this->groupIsOptionsPage($group)) {
            $coverage = '— (options page)';
        }

        return [
            'post_type'        => $postType,
            'coverage'         => $coverage,
            'mismatch_count'   => $mismatchCount,
            'mismatch_display' => $mismatchDisplay,
        ];
    }

    private function renderGroupRow(string $key, FieldGroup $group, array $data): void
    {
        $context    = $this->buildContextLabel($group);
        $fieldCount = count($group->getFields());
        $postType   = $data['post_type'];

        $csvCell = '—';
        if ($postType !== null) {
            $exportUrl = add_query_arg([
                'page'          => 'fieldforge-inspector',
                'action'        => 'fieldforge_export_csv',
                'group'         => $key,
                '_fieldforge_nonce' => wp_create_nonce('fieldforge_csv_export_' . $key),
            ], admin_url('admin.php'));

            $csvCell = sprintf(
                '<a href="%s" class="button button-small">Export CSV</a>'
                . ' <label class="button button-small" style="cursor:pointer;margin:0">'
                . 'Import CSV'
                . '<input type="file" accept=".csv" style="display:none" data-ff-group="%s" data-ff-nonce="%s">'
                . '</label>',
                esc_url($exportUrl),
                esc_attr($key),
                esc_attr((string) wp_create_nonce('fieldforge_csv_import_' . $key)),
            );
        }

        printf(
            '<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($key),
            esc_html($group->getTitle() ?: '—'),
            esc_html($context),
            $fieldCount,
            esc_html($data['coverage']),
            esc_html($data['mismatch_display']),
            $csvCell,
        );

        // Expandable field detail row
        echo '<tr><td colspan="7" style="padding:0">';
        echo '<details style="padding:8px 12px;background:#f9f9f9">';
        echo '<summary style="cursor:pointer;font-weight:600">Field details</summary>';
        echo '<table style="width:100%;margin-top:8px;border-collapse:collapse">';
        echo '<thead><tr style="border-bottom:1px solid #ddd">';
        echo '<th style="text-align:left;padding:4px 8px">Key</th>';
        echo '<th style="text-align:left;padding:4px 8px">Label</th>';
        echo '<th style="text-align:left;padding:4px 8px">Type</th>';
        echo '<th style="text-align:left;padding:4px 8px">Index</th>';
        echo '<th style="text-align:left;padding:4px 8px">REST</th>';
        echo '<th style="text-align:left;padding:4px 8px">Column</th>';
        echo '<th style="text-align:left;padding:4px 8px">Translatable</th>';
        echo '<th style="text-align:left;padding:4px 8px">Condition</th>';
        echo '</tr></thead><tbody>';

        foreach ($group->getFields() as $field) {
            $def = $field->getDefinition();
            $cg  = $field->getConditionGroup();

            $conditionText = '—';
            if ($cg !== null) {
                $parts = [];
                foreach ($cg->getConditions() as $c) {
                    $parts[] = "{$c[0]} {$c[1]} {$c[2]}";
                }
                $conditionText = strtoupper($cg->getType()) . ': ' . implode(', ', $parts);
            }

            printf(
                '<tr style="border-bottom:1px solid #eee">'
                . '<td style="padding:4px 8px"><code>%s</code></td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '<td style="padding:4px 8px"><code>%s</code></td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '<td style="padding:4px 8px">%s</td>'
                . '</tr>',
                esc_html($def['key']),
                esc_html($def['label'] ?: '—'),
                esc_html($def['type']),
                $def['index'] ? '✓' : '—',
                $def['rest_exposed'] ? '✓' : '—',
                $def['admin_column'] ? '✓' : '—',
                ($def['translatable'] ?? true) ? '✓ Yes' : '— Shared',
                esc_html($conditionText),
            );
        }

        echo '</tbody></table></details></td></tr>';
    }

    private function queryCoverage(string $postType): string
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return 'N/A';
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $postType,
        ));

        if ($total === 0) {
            return '0 posts';
        }

        $withData = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_fieldforge_data'
             AND post_id IN (
                 SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_status = 'publish'
             )",
            $postType,
        ));

        $pct = $total > 0 ? round(($withData / $total) * 100) : 0;

        return "{$withData}/{$total} ({$pct}%)";
    }

    /** @return array{0: int, 1: string} */
    private function queryVersionMismatch(string $postType): array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return [0, '✓ Current'];
        }

        // JSON_EXTRACT requires MySQL 5.7+. Detect support via a cheap test.
        $hasJsonExtract = $this->supportsJsonExtract();

        if (! $hasJsonExtract) {
            return [0, 'Unknown (JSON_EXTRACT unavailable)'];
        }

        $current = SchemaVersion::CURRENT;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_fieldforge_data'
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)
             AND CAST(JSON_EXTRACT(meta_value, '$.schema_version') AS UNSIGNED) < %d",
            $postType,
            $current,
        ));

        if ($count === 0) {
            return [0, '✓ Current'];
        }

        return [$count, sprintf('⚠ Mismatch: %d post%s (DB < v%d)', $count, $count === 1 ? '' : 's', $current)];
    }

    private function supportsJsonExtract(): bool
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return false;
        }

        try {
            $result = $wpdb->get_var("SELECT JSON_EXTRACT('{\"v\":1}', '$.v')");
            return $result !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function groupIsOptionsPage(FieldGroup $group): bool
    {
        foreach ($group->getAndConditions() as $condition) {
            if ($condition['key'] === 'options_page') {
                return true;
            }
        }
        return false;
    }

    private function renderCsvAjaxHandler(): void
    {
        // Handle inline CSV export (direct download from admin page)
        $action = $_GET['action'] ?? '';
        $group  = $_GET['group']  ?? '';
        $nonce  = $_GET['_fieldforge_nonce'] ?? '';

        if ($action === 'fieldforge_export_csv' && is_string($group) && $group !== '') {
            if (! wp_verify_nonce((string) $nonce, 'fieldforge_csv_export_' . $group)) {
                wp_die('Security check failed.', 403);
            }

            $postType = $this->resolvePostTypeForGroup($group);
            if ($postType === null) {
                wp_die('Group not found or has no post_type context.', 400);
            }

            header('Content-Type: text/csv; charset=UTF-8');
            header(sprintf('Content-Disposition: attachment; filename="%s-%s.csv"', $postType, $group));

            $exporter = new CsvExporter();
            foreach ($exporter->export($postType) as $row) {
                echo $row; // phpcs:ignore WordPress.Security.EscapeOutput
            }
            exit;
        }
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

    private function buildContextLabel(FieldGroup $group): string
    {
        $parts = [];

        foreach ($group->getAndConditions() as $c) {
            $parts[] = "{$c['key']} {$c['operator']} {$c['value']}";
        }

        foreach ($group->getOrGroups() as $orGroup) {
            $orParts = [];
            foreach ($orGroup as $c) {
                $orParts[] = "{$c['key']} {$c['operator']} {$c['value']}";
            }
            $parts[] = 'OR(' . implode(', ', $orParts) . ')';
        }

        return empty($parts) ? '(no conditions)' : implode(' AND ', $parts);
    }
}
