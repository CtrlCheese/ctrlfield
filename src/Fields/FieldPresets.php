<?php

declare(strict_types=1);

namespace CtrlField\Fields;

use CtrlField\Fields\Exceptions\PresetNotFoundException;

/**
 * Extensible registry for reusable field snippets.
 *
 * Zero built-in presets — the developer registers everything. Each preset
 * is a factory Closure so every call to get() / the magic static returns
 * a fresh, independent FieldDefinition instance.
 *
 * Usage:
 *
 *   // Register (theme's functions.php or a shared config file)
 *   FieldPresets::register('theme', fn() =>
 *       Field::select('theme')
 *           ->label('Theme')
 *           ->options(['light' => 'Light', 'dark' => 'Dark'])
 *   );
 *
 *   // Retrieve — each call returns a new instance
 *   FieldPresets::theme()        // magic static
 *   FieldPresets::get('theme')   // explicit
 *
 *   // Introspection
 *   FieldPresets::has('theme')   // bool
 *   FieldPresets::keys()         // string[]
 *
 * @method static FieldDefinition theme()
 * @method static FieldDefinition size()
 * @method static FieldDefinition spacing()
 */
final class FieldPresets
{
    /** @var array<string, \Closure(): FieldDefinition> */
    private static array $presets = [];

    /**
     * Registers a named preset factory.
     *
     * @param \Closure(): FieldDefinition $factory
     */
    public static function register(string $name, \Closure $factory): void
    {
        self::$presets[$name] = $factory;
    }

    /**
     * Returns a new FieldDefinition instance for the named preset.
     *
     * @throws PresetNotFoundException When the preset has not been registered.
     */
    public static function get(string $name): FieldDefinition
    {
        if (! isset(self::$presets[$name])) {
            throw new PresetNotFoundException(
                "FieldPreset '{$name}' is not registered. "
                . "Register it with FieldPresets::register('{$name}', fn() => Field::select('{$name}')->...).",
            );
        }

        return (self::$presets[$name])();
    }

    /**
     * Returns true when a preset with the given name is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$presets[$name]);
    }

    /**
     * Returns the names of all registered presets.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::$presets);
    }

    /**
     * Resets all presets. Intended for use in tests only.
     */
    public static function reset(): void
    {
        self::$presets = [];
    }

    /**
     * Magic static: FieldPresets::theme() delegates to FieldPresets::get('theme').
     *
     * @param string        $name Method name used as the preset key.
     * @param array<mixed>  $args Unused.
     *
     * @return FieldDefinition
     *
     * @throws PresetNotFoundException When the preset has not been registered.
     */
    public static function __callStatic(string $name, array $args): FieldDefinition
    {
        return self::get($name);
    }
}
