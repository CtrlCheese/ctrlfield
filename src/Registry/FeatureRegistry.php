<?php

declare(strict_types=1);

namespace FieldForge\Registry;

/**
 * Pro feature hook-in point.
 *
 * Core never imports Pro code. Pro registers features here at boot time.
 * Core checks existence before delegating rendering or processing.
 *
 * Example (Pro plugin):
 *   FeatureRegistry::register('flexible_content', Pro\FlexibleContent::class);
 *
 * Example (Core check):
 *   if (FeatureRegistry::has('flexible_content')) { ... }
 */
class FeatureRegistry
{
    /** @var array<string, class-string> */
    private static array $features = [];

    /** @param class-string $class */
    public static function register(string $feature, string $class): void
    {
        self::$features[$feature] = $class;
    }

    public static function has(string $feature): bool
    {
        return isset(self::$features[$feature]);
    }

    /** @return class-string|null */
    public static function get(string $feature): ?string
    {
        return self::$features[$feature] ?? null;
    }

    /** @return array<string, class-string> */
    public static function all(): array
    {
        return self::$features;
    }

    public static function reset(): void
    {
        self::$features = [];
    }
}
