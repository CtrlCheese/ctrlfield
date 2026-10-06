<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

final class OptionsPageResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext) {
            return false;
        }

        return $operator === '=='
            ? $screen->optionsPage === $value
            : $screen->optionsPage !== $value;
    }
}
