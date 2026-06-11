<?php

declare(strict_types=1);

namespace FieldForge\Admin\Comment;

use FieldForge\Bootstrap\ServiceProvider;

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
