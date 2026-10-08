<?php

declare(strict_types=1);

namespace CtrlField\Registry;

use CtrlField\Bootstrap\Exceptions\NotFoundException;
use CtrlField\Builder\Exceptions\DuplicateGroupKeyException;
use CtrlField\Builder\FieldGroup;

class FieldRegistry
{
    /** @var array<string, FieldGroup> */
    private static array $groups = [];

    public static function add(FieldGroup $group): void
    {
        $key = $group->getKey();

        if (isset(self::$groups[$key])) {
            throw new DuplicateGroupKeyException(
                "A field group with key '{$key}' is already registered."
            );
        }

        self::$groups[$key] = $group;
    }

    public static function get(string $key): FieldGroup
    {
        if (! isset(self::$groups[$key])) {
            throw new NotFoundException("No field group found with key '{$key}'.");
        }

        return self::$groups[$key];
    }

    public static function has(string $key): bool
    {
        return isset(self::$groups[$key]);
    }

    /** @return array<string, FieldGroup> */
    public static function all(): array
    {
        return self::$groups;
    }

    /** Unregister one group (ACF local groups that receive fields after registration). */
    public static function remove(string $key): void
    {
        unset(self::$groups[$key]);
    }

    public static function reset(): void
    {
        self::$groups = [];
    }
}
