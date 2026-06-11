<?php

declare(strict_types=1);

namespace FieldForge\Admin\Taxonomy;

use FieldForge\Bootstrap\ServiceProvider;

class TaxonomyMetaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('init', function () {
            (new TaxonomyMetaRegistrar())->register();
        }, 20); // after CPT/taxonomy registration (default priority 10)
    }
}
