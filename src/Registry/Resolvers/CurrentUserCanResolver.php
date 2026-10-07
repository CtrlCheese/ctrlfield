<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('current_user_can', '==', 'manage_options') */
final class CurrentUserCanResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        return PostRuleOperator::compare($operator, current_user_can((string) $value));
    }
}
