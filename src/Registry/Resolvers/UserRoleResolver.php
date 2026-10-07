<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('user_role', '==', 'author') — role of the user being edited (profile screens) */
final class UserRoleResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->userId === null) {
            return false;
        }
        $user = get_userdata($screen->userId);

        return PostRuleOperator::compare($operator, $user !== false && in_array((string) $value, (array) $user->roles, true));
    }
}
