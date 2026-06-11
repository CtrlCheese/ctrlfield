<?php

declare(strict_types=1);

namespace FieldForge\Registry\Resolvers;

use FieldForge\Builder\AdminContext;
use FieldForge\Registry\Contracts\ContextInterface;

final class TaxonomyResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext) {
            return false;
        }

        return $operator === '=='
            ? $screen->taxonomy === $value
            : $screen->taxonomy !== $value;
    }
}
