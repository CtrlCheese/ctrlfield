<?php

declare(strict_types=1);

namespace CtrlField\Storage\Drivers;

/**
 * Production driver: delegates to WordPress options functions.
 * Excluded from PHPStan — WP functions are not available outside WP runtime.
 */
class WpOptionsDriver implements OptionsDriverInterface
{
    public function update(string $key, mixed $value, string $autoload): void
    {
        update_option($key, $value, $autoload);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return get_option($key, $default);
    }
}
