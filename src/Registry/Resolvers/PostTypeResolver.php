<?php

declare(strict_types=1);

namespace FieldForge\Registry\Resolvers;

use FieldForge\Builder\AdminContext;
use FieldForge\Registry\Contracts\ContextInterface;

final class PostTypeResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext) {
            return false;
        }

        return $operator === '=='
            ? $screen->postType === $value
            : $screen->postType !== $value;
    }
}
