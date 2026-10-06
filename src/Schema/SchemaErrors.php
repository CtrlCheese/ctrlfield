<?php

declare(strict_types=1);

namespace CtrlField\Schema;

use Throwable;

/**
 * Schema files that failed to load during this request.
 *
 * A mistake in one schema file (an invalid width, adminColumn() without
 * setIndex(), …) must not take the whole site down: the loader skips the file,
 * records the error here and admins see it in a notice.
 */
final class SchemaErrors
{
    /** @var array<string, string> file path => error message */
    private static array $errors = [];

    public static function add(string $file, Throwable $e): void
    {
        self::$errors[$file] = $e->getMessage();
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$errors;
    }

    public static function reset(): void
    {
        self::$errors = [];
    }
}
