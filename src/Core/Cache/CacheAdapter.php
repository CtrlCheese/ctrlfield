<?php

declare(strict_types=1);

namespace FieldForge\Core\Cache;

/**
 * Two-layer cache: static PHP array (request-scoped) + WP Object Cache (cross-request).
 *
 * The static layer requires zero WP dependency and ensures a single DB hydration
 * per request regardless of how many times load() is called for the same key.
 *
 * The WP Object Cache layer (Redis/Memcached) is used only when WP is loaded
 * and a persistent cache backend is active. Calls are guarded by function_exists()
 * so this class works in pure PHP unit tests without WP present.
 */
class CacheAdapter
{
    private const GROUP = 'fieldforge';

    /** @var array<string, mixed> */
    private static array $staticCache = [];

    public static function get(string $key): mixed
    {
        if (array_key_exists($key, self::$staticCache)) {
            return self::$staticCache[$key];
        }

        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($key, self::GROUP);

            if ($value !== false) {
                self::$staticCache[$key] = $value;
                return $value;
            }
        }

        return null;
    }

    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        self::$staticCache[$key] = $value;

        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, self::GROUP, $ttl);
        }
    }

    public static function invalidate(string $key): void
    {
        unset(self::$staticCache[$key]);

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, self::GROUP);
        }
    }

    public static function flush(): void
    {
        self::$staticCache = [];
    }

    /** @return array<string, mixed> */
    public static function dump(): array
    {
        return self::$staticCache;
    }
}
