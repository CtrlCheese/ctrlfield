<?php

declare(strict_types=1);

namespace CtrlField\Admin;

/**
 * A value as a JavaScript literal inside an HTML attribute that Alpine or a
 * script reads: x-data="ctrlfForm(<?php echo esc_attr(Js::json($config)); ?>)".
 *
 * Not esc_attr(wp_json_encode(…)): esc_attr() keeps entities it finds, so a
 * stored "&quot;" reaches the browser as a quote, closes the JSON string and
 * runs whatever follows. Js::json() writes &, quotes and <> as \u00XX, so
 * the JSON holds no entity for esc_attr() to keep.
 */
final class Js
{
    public const FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    /** The JSON literal for an attribute: the same as esc_attr(Js::json()). */
    public static function attr(mixed $value): string
    {
        return htmlspecialchars(self::json($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** The JSON literal for a <script> block or a JS string built in PHP. */
    public static function json(mixed $value): string
    {
        return (string) (json_encode($value, self::FLAGS) ?: 'null');
    }
}
