<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Bootstrap\ServiceProvider;

/**
 * ACF compatibility: ACF's template functions (when ACF is not active), the
 * acf/init hooks themes register groups on, and the ACF importer.
 */
final class AcfServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // After every plugin is loaded, so an active ACF is detected.
        add_action('plugins_loaded', [$this, 'loadApi'], 20);

        $page = new AcfImportPage();
        add_action('admin_menu', [$page, 'registerMenu'], 20);
        add_action('admin_post_ctrlfield_acf_import', [$page, 'handleImport']);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('ctrlfield acf-import', AcfImportCommand::class);
        }
    }

    public function loadApi(): void
    {
        if (class_exists('ACF') || ! apply_filters('ctrlfield/acf_compat', true)) {
            return;
        }

        require_once __DIR__ . '/functions.php';

        // Themes register ACF groups and options pages on these hooks.
        add_action('init', static function (): void {
            do_action('acf/init');
            do_action('acf/include_options_pages');
            do_action('acf/include_fields');
            AcfApi::registerPendingGroups();
        }, 6);
    }
}
