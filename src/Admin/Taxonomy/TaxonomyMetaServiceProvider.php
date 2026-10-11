<?php

declare(strict_types=1);

namespace CtrlField\Admin\Taxonomy;

use CtrlField\Bootstrap\ServiceProvider;

class TaxonomyMetaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // wp_loaded: after every init callback, so groups registered on init
        // (any priority, e.g. a theme on init 20) are known. The term form and
        // save hooks fire later in the request.
        add_action('wp_loaded', function () {
            (new TaxonomyMetaRegistrar())->register();
        });
    }
}
