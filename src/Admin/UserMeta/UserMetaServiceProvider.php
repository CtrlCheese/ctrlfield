<?php

declare(strict_types=1);

namespace CtrlField\Admin\UserMeta;

use CtrlField\Bootstrap\ServiceProvider;

class UserMetaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        (new UserMetaRegistrar())->register();
    }
}
