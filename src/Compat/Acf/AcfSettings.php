<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

/** acf_get_setting() / acf_update_setting(): the few settings themes read. */
final class AcfSettings
{
    /** @var array<string, mixed> */
    private static array $settings = [];

    public static function get(string $name, mixed $default = null): mixed
    {
        $defaults = [
            'version'          => '6.3.0',
            'default_language' => '',
            'current_language' => '',
            'show_admin'       => false,
            'save_json'        => '',
            'load_json'        => [],
            'l10n'             => true,
            'pro'              => true,
        ];
        $value = self::$settings[$name] ?? ($defaults[$name] ?? $default);

        return function_exists('apply_filters') ? apply_filters("acf/settings/{$name}", $value) : $value;
    }

    public static function set(string $name, mixed $value): void
    {
        self::$settings[$name] = $value;
    }
}
