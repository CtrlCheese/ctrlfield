<?php

declare(strict_types=1);

namespace CtrlField\Admin\Columns;

use CtrlField\Bootstrap\ServiceProvider;

final class ColumnsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_init', static function () {
            (new AdminColumnRegistrar())->register();
        });
    }
}
