<?php

declare(strict_types=1);

namespace FieldForge\Admin\UserMeta;

use FieldForge\Bootstrap\ServiceProvider;

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
