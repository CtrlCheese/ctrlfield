<?php

declare(strict_types=1);

namespace FieldForge\Admin\UserMeta;

use FieldForge\Admin\MetaBox\MetaBoxRenderer;
use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\UserMetaAdapter;

/**
 * Registers FieldForge field groups on WordPress user screens.
 * Excluded from PHPStan — references WP functions.
 */
class UserMetaRegistrar
{
    public function register(): void
    {
        add_action('show_user_profile',    [$this, 'render']);
        add_action('edit_user_profile',    [$this, 'render']);
        add_action('user_new_form',        [$this, 'render']);

        add_action('personal_options_update',  [$this, 'save']);
        add_action('edit_user_profile_update', [$this, 'save']);
    }

    public function render(\WP_User $user): void
    {
        $context = new AdminContext(contextType: 'user_profile');
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        $renderer = new MetaBoxRenderer();
        $renderer->render($user->ID, array_values($groups));
    }

    public function save(int $userId): void
    {
        if (! isset($_POST['fieldforge_payload'])) {
            return;
        }

        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        SavePipeline::run(
            entityId:        $userId,
            rawPost:         $_POST,
            adapterOverride: new UserMetaAdapter(),
            contextOverride: new AdminContext(contextType: 'user_profile'),
        );
    }
}
