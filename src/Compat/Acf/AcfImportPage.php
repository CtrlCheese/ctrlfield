<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Admin\FieldGroups\FieldGroupRepository;
use CtrlField\Admin\FieldGroups\FieldGroupsPage;

/**
 * CtrlField → Field Groups → Import from ACF.
 */
final class AcfImportPage
{
    public const SLUG      = 'ctrlfield-acf-import';
    private const CAP      = 'manage_options';
    private const REPORT_TX = 'ctrlfield_acf_import_report_';

    public function registerMenu(): void
    {
        // Hidden page: reached from the Field Groups list.
        add_submenu_page('', __('Import from ACF', 'ctrlfield'), '', self::CAP, self::SLUG, [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ctrlfield'));
        }

        $importer = new AcfImporter(new FieldGroupRepository());
        $sources  = $importer->sources();
        $report   = get_transient(self::REPORT_TX . get_current_user_id());
        delete_transient(self::REPORT_TX . get_current_user_id());
        $back     = add_query_arg(['page' => FieldGroupsPage::SLUG], admin_url('admin.php'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import from ACF', 'ctrlfield'); ?></h1>
            <p><a href="<?php echo esc_url($back); ?>">&larr; <?php esc_html_e('All field groups', 'ctrlfield'); ?></a></p>

            <?php if (is_array($report) && array_is_list($report)) {
                /** @var list<array{title: string, key: string, status: string, warnings: list<string>, errors: list<string>, objects: int, skipped: list<string>}> $report */
                $this->renderReport($report);
            } ?>

            <?php if (class_exists('ACF')): ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('ACF is active. After the import, deactivate it: while both run, the fields appear twice and get_field() keeps reading ACF\'s data.', 'ctrlfield'); ?></p></div>
            <?php endif; ?>

            <p class="description" style="max-width:760px">
                <?php esc_html_e('Field groups are converted into CtrlField groups (JSON). ACF\'s own data is only read: nothing is changed or deleted, so you can switch back at any time. Templates keep working: get_field(), the_field(), have_rows() and update_field() are provided by CtrlField when ACF is not active.', 'ctrlfield'); ?>
            </p>

            <?php if ($sources === []): ?>
                <p><strong><?php esc_html_e('No ACF field groups were found in the database or in acf-json folders.', 'ctrlfield'); ?></strong></p>
            <?php else: ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ctrlfield_acf_import">
                    <?php wp_nonce_field('ctrlfield_acf_import'); ?>
                    <table class="wp-list-table widefat fixed striped" style="max-width:960px;margin-top:12px">
                        <thead><tr>
                            <td class="check-column"><input type="checkbox" checked onclick="document.querySelectorAll('.cf-acf-group').forEach(c => c.checked = this.checked)"></td>
                            <th><?php esc_html_e('Field group', 'ctrlfield'); ?></th>
                            <th style="width:80px"><?php esc_html_e('Fields', 'ctrlfield'); ?></th>
                            <th><?php esc_html_e('Source', 'ctrlfield'); ?></th>
                            <th><?php esc_html_e('Status', 'ctrlfield'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($sources as $key => $src): ?>
                            <tr>
                                <th class="check-column"><input type="checkbox" class="cf-acf-group" name="groups[]" value="<?php echo esc_attr($key); ?>" checked></th>
                                <td><strong><?php echo esc_html((string) ($src['acf']['title'] ?? $key)); ?></strong><br><code><?php echo esc_html($key); ?></code></td>
                                <td><?php echo esc_html((string) count((array) ($src['acf']['fields'] ?? []))); ?></td>
                                <td><?php echo $src['source'] === 'json' ? 'acf-json' : esc_html__('Database', 'ctrlfield'); ?></td>
                                <td><?php echo $src['imported'] !== null
                                    ? esc_html(sprintf(__('Imported as %s (importing again updates it)', 'ctrlfield'), $src['imported']))
                                    : esc_html__('Not imported', 'ctrlfield'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <label><input type="checkbox" name="with_data" value="1" checked>
                            <?php esc_html_e('Also copy the values saved with ACF (posts, terms, users and options pages)', 'ctrlfield'); ?></label>
                    </p>
                    <?php submit_button(__('Import', 'ctrlfield')); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @param list<array{title: string, key: string, status: string, warnings: list<string>, errors: list<string>, objects: int, skipped: list<string>}> $report */
    private function renderReport(array $report): void
    {
        ?>
        <div class="notice notice-success"><p><strong><?php esc_html_e('Import finished.', 'ctrlfield'); ?></strong></p></div>
        <table class="wp-list-table widefat fixed striped" style="max-width:960px;margin-bottom:24px">
            <thead><tr>
                <th><?php esc_html_e('Field group', 'ctrlfield'); ?></th>
                <th><?php esc_html_e('Result', 'ctrlfield'); ?></th>
                <th style="width:120px"><?php esc_html_e('Items with values', 'ctrlfield'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($report as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r['title']); ?></strong><br><code><?php echo esc_html($r['key']); ?></code></td>
                    <td>
                        <?php echo $r['status'] === 'failed' ? '<strong style="color:#b32d2e">' . esc_html__('Not imported', 'ctrlfield') . '</strong>' : esc_html__('Imported', 'ctrlfield'); ?>
                        <?php foreach (array_merge($r['errors'], $r['warnings'], $r['skipped']) as $msg): ?>
                            <br><span class="description">• <?php echo esc_html($msg); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?php echo esc_html((string) $r['objects']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function handleImport(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'ctrlfield'), 403);
        }
        check_admin_referer('ctrlfield_acf_import');

        $keys = array_map(
            static fn ($k) => sanitize_text_field((string) $k),
            (array) wp_unslash($_POST['groups'] ?? [])
        );
        $report = (new AcfImporter(new FieldGroupRepository()))->import(array_values($keys), ! empty($_POST['with_data']));

        set_transient(self::REPORT_TX . get_current_user_id(), $report, 600);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG], admin_url('admin.php')));
        exit;
    }
}
