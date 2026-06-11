<?php

declare(strict_types=1);

namespace FieldForge\Integrations\REST;

use FieldForge\Bootstrap\ServiceProvider;

/**
 * Registers the public-facing FieldForge REST routes on rest_api_init.
 *
 * Routes registered:
 *   GET fieldforge/v1/post/{post_id}      — showInRest field values
 *   GET fieldforge/v1/schema/{post_type}  — public schema (types + labels)
 *   GET fieldforge/v1/user/{user_id}      — user fields with showInRest(true)
 *   GET fieldforge/v1/term/{term_id}      — term fields with showInRest(true)
 */
class RestServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        (new FieldsController())->register();
        (new UserFieldsController())->register();
        (new TermFieldsController())->register();
        (new SearchController())->register();
    }
}
