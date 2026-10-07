<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('current_user_role', '==', 'editor') — role of the person using the admin */
final class CurrentUserRoleResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        $user = wp_get_current_user();

        return PostRuleOperator::compare($operator, $user->exists() && in_array((string) $value, (array) $user->roles, true));
    }
}
