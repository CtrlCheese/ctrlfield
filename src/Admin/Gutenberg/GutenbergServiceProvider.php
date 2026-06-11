<?php

declare(strict_types=1);

namespace FieldForge\Admin\Gutenberg;

use FieldForge\Bootstrap\ServiceProvider;

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
