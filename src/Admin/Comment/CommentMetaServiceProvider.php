<?php

declare(strict_types=1);

namespace CtrlField\Admin\Comment;

use CtrlField\Bootstrap\ServiceProvider;

class CommentMetaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        (new CommentMetaRegistrar())->register();
    }
}
