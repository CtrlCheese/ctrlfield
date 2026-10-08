<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Bootstrap\ServiceProvider;

/**
 * ACF compatibility: ACF's template functions (when ACF is not active) and
 * the acf/init hooks themes register groups on. The importer is Pro
 * (pro/src/Compat/Acf).
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
    }

    public function loadApi(): void
    {
        if (AcfApi::isRealAcf() || ! apply_filters('ctrlfield/acf_compat', true)) {
            return;
        }

        require_once __DIR__ . '/functions.php';

        AcfFieldType::registerAll();

        // ACF's admin filters, on the CtrlField hooks they correspond to.
        add_filter('ctrlfield/prepare_field', [AcfApi::class, 'prepareField'], 10, 3);
        add_filter('ctrlfield/flex_layout_title', [AcfApi::class, 'layoutTitle'], 10, 3);

        // Themes register ACF groups and options pages on these hooks.
        add_action('init', static function (): void {
            do_action('acf/init');
            do_action('acf/include_options_pages');
            do_action('acf/include_fields');
            AcfApi::markAcfInitDone();
        }, 6);

        // Local groups register once init is done, so fields added later with
        // acf_add_local_field() (Flynt options) are part of them.
        add_action('init', [AcfApi::class, 'flushLocalGroups'], 99);
    }
}
