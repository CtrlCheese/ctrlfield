<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Translation;

use CtrlField\Bootstrap\ServiceProvider;

/**
 * Registers WPML / Polylang hooks only when a translation plugin is active.
 * No-op when neither is present — zero overhead on standard installs.
 */
final class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! TranslationBridge::isActive()) {
            return;
        }

        $handler = new TranslationSyncHandler();

        add_action('ctrlfield/after_save', [$handler, 'syncSharedFields'], 10, 2);
        add_action('pll_after_copy',        [$handler, 'onPolylangCopy'],   10, 3);
        add_action('wpml_after_copy_meta',  [$handler, 'onWpmlCopy'],       10, 3);
    }
}
