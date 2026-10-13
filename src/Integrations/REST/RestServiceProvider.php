<?php

declare(strict_types=1);

namespace CtrlField\Integrations\REST;

use CtrlField\Bootstrap\ServiceProvider;

/**
 * Registers the public-facing CtrlField REST routes on rest_api_init.
 *
 * Routes registered:
 *   GET ctrlfield/v1/post/{post_id}      — showInRest field values
 *   GET ctrlfield/v1/schema/{post_type}  — public schema (types + labels)
 *   GET ctrlfield/v1/user/{user_id}      — user fields with showInRest(true)
 *   GET ctrlfield/v1/term/{term_id}      — term fields with showInRest(true)
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

        // The Link dialog's list of anchors across the site is cached; any saved
        // or deleted post may add or remove anchors.
        foreach (['save_post', 'deleted_post', 'ctrlfield/after_save'] as $hook) {
            add_action($hook, [LinkController::class, 'forgetSiteAnchors']);
        }
    }

    public function registerRoutes(): void
    {
        (new FieldsController())->register();
        (new UserFieldsController())->register();
        (new TermFieldsController())->register();
        (new SearchController())->register();
        (new LinkController())->register();
        (new PickerController())->register();
    }
}
