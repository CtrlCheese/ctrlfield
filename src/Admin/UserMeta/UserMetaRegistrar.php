<?php

declare(strict_types=1);

namespace CtrlField\Admin\UserMeta;

use CtrlField\Admin\EntitySave;
use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Builder\AdminContext;
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
        // Users → Add New (user-new.php) shows the fields too.
        add_action('user_register',            [$this, 'saveNew']);

        add_action('admin_notices', [$this, 'printSaveError']);
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

        $stored = $userId > 0 ? ((new UserMetaAdapter())->load($userId) ?? []) : [];

        $renderer = new MetaBoxRenderer();
        $renderer->render($userId, array_values($groups), $stored);
    }

    public function save(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        EntitySave::run('user', $userId, new UserMetaAdapter(), AdminContext::forUser($userId), 'edit_user');
    }

    /** user_register also fires for front-end sign-ups: only the admin form posts a payload. */
    public function saveNew(int $userId): void
    {
        if (! is_admin() || ! isset($_POST['ctrlfield_payload'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only; save() → pipeline verifies the nonce
            return;
        }

        $this->save($userId);
    }

    /** Error of the last save, on profile.php / user-edit.php. */
    public function printSaveError(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || ! in_array($screen->base, ['profile', 'user-edit'], true)) {
            return;
        }
        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which notice to show

        EntitySave::printError('user', $userId);
    }
}
