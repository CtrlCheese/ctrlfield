<?php

declare(strict_types=1);

namespace CtrlField\Admin\UserMeta;

use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\UserMetaAdapter;

/**
 * Registers CtrlField field groups on WordPress user screens.
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

    /** @param \WP_User|string $user user_new_form passes a string ('add-new-user'), not a user. */
    public function render(mixed $user): void
    {
        $userId  = $user instanceof \WP_User ? $user->ID : 0;
        $context = AdminContext::forUser($userId);
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        $renderer = new MetaBoxRenderer();
        $renderer->render($userId, array_values($groups));
    }

    public function save(int $userId): void
    {
        if (! isset($_POST['ctrlfield_payload'])) {
            return;
        }

        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        SavePipeline::run(
            entityId:        $userId,
            rawPost:         $_POST,
            adapterOverride: new UserMetaAdapter(),
            contextOverride: AdminContext::forUser($userId),
        );
    }
}
