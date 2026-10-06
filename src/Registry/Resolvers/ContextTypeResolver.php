<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/**
 * Resolves the 'context' key used for user_profile and comment screens.
 * ->where('context', '==', 'user_profile')
 * ->where('context', '==', 'comment')
 */
final class ContextTypeResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext) {
            return false;
        }

        return $operator === '=='
            ? $screen->contextType === $value
            : $screen->contextType !== $value;
    }
}
