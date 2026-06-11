<?php

declare(strict_types=1);

namespace FieldForge\Registry;

use FieldForge\Bootstrap\Exceptions\NotFoundException;
use FieldForge\Builder\Exceptions\DuplicateGroupKeyException;
use FieldForge\Builder\FieldGroup;

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

    public static function reset(): void
    {
        self::$groups = [];
    }
}
