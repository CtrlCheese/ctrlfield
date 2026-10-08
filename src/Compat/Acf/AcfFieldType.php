<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Fields\FieldDefinition;

/**
 * What acf_get_field_type() returns. As in ACF, each type formats its values
 * on the acf/format_value/type={type} filter (priority 10), so code that swaps
 * that callback for its own — Timber's ACF integration does, to return
 * Timber\Image objects — works the same.
 */
final class AcfFieldType
{
    /** @var array<string, self> */
    private static array $instances = [];

    private static bool $hooked = false;

    public function __construct(public readonly string $name) {}

    public static function get(string $acfType): self
    {
        return self::$instances[$acfType] ??= new self($acfType);
    }

    /** Hook every type's formatter (done once, when the ACF API is loaded). */
    public static function registerAll(): void
    {
        if (self::$hooked || ! function_exists('add_filter')) {
            return;
        }
        foreach (array_keys(AcfConverter::TYPES) as $acfType) {
            add_filter("acf/format_value/type={$acfType}", [self::get($acfType), 'format_value'], 10, 3);
        }
        self::$hooked = true;
    }

    public static function hooked(): bool
    {
        return self::$hooked;
    }

    /**
     * @param array<string, mixed> $field ACF field array (with the CtrlField definition in _ctrlfield)
     */
    public function format_value(mixed $value, mixed $postId, array $field): mixed // phpcs:ignore -- ACF's method name
    {
        $def = $field['_ctrlfield'] ?? null;

        return $def instanceof FieldDefinition ? AcfValues::formatType($value, $def) : $value;
    }
}
