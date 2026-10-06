<?php

declare(strict_types=1);

namespace CtrlField\Admin\Gutenberg;

use CtrlField\Bootstrap\ServiceProvider;

class GutenbergServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        (new GutenbergBootstrap())->boot();
    }
}
