<?php

declare(strict_types=1);

namespace CtrlField\Data;

/**
 * The icon sets of the Icon field (assets/icons/*.json, built by
 * bin/build-icons.mjs) and the HTML of one icon.
 *
 * Values: "dashicons-admin-site" (WordPress) or "lucide:arrow-right". Lucide
 * icons are printed as inline SVG, so the site loads nothing extra.
 */
final class IconLibrary
{
    public const LIBRARIES = ['lucide', 'dashicons'];

    private const NAME = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<string, string>|null name => svg body */
    private static ?array $lucide = null;

    /**
     * "lucide:arrow-right" → ['lucide', 'arrow-right']; ['', ''] when not an icon value.
     *
     * @return array{0: string, 1: string}
     */
    public static function parse(mixed $value): array
    {
        $v = is_string($value) ? trim($value) : '';
        if (str_starts_with($v, 'lucide:') && preg_match(self::NAME, substr($v, 7))) {
            return ['lucide', substr($v, 7)];
        }
        if (str_starts_with($v, 'dashicons-') && preg_match(self::NAME, substr($v, 10))) {
            return ['dashicons', substr($v, 10)];
        }
        return ['', ''];
    }

    /** Whether the value can be stored for an Icon field (sanitizer). */
    public static function isValid(mixed $value): bool
    {
        return self::parse($value)[0] !== '';
    }

    /**
     * The icon as HTML: a Dashicons span, or Lucide's SVG inline.
     *
     * @param array<string, scalar> $attrs Extra attributes (class, width, height, stroke-width, aria-label…).
     */
    public static function html(mixed $value, array $attrs = []): string
    {
        [$lib, $name] = self::parse($value);
        $label = isset($attrs['aria-label']) ? (string) $attrs['aria-label'] : '';
        $a11y  = $label !== '' ? ' role="img" aria-label="' . esc_attr($label) . '"' : ' aria-hidden="true"';
        unset($attrs['aria-label']);

        if ($lib === 'dashicons') {
            if (function_exists('wp_enqueue_style') && ! is_admin()) {
                wp_enqueue_style('dashicons');
            }
            $class = trim('dashicons dashicons-' . $name . ' ' . (string) ($attrs['class'] ?? ''));
            return '<span class="' . esc_attr($class) . '"' . $a11y . '></span>';
        }

        if ($lib !== 'lucide') {
            return '';
        }
        $body = self::lucide()[$name] ?? null;
        if ($body === null) {
            return '';
        }

        $attrs += [
            'xmlns'           => 'http://www.w3.org/2000/svg',
            'width'           => 24,
            'height'          => 24,
            'viewBox'         => '0 0 24 24',
            'fill'            => 'none',
            'stroke'          => 'currentColor',
            'stroke-width'    => 2,
            'stroke-linecap'  => 'round',
            'stroke-linejoin' => 'round',
        ];
        $attrs['class'] = trim('lucide lucide-' . $name . ' ' . (string) ($attrs['class'] ?? ''));

        $out = '<svg';
        foreach ($attrs as $key => $val) {
            $out .= ' ' . preg_replace('/[^a-zA-Z0-9:_-]/', '', (string) $key) . '="' . esc_attr((string) $val) . '"';
        }
        // The body comes from the bundled Lucide build, not from user input.
        return $out . $a11y . ' focusable="false">' . $body . '</svg>';
    }

    /** @return array<string, string> */
    private static function lucide(): array
    {
        if (self::$lucide !== null) {
            return self::$lucide;
        }
        $file = dirname(__DIR__, 2) . '/assets/icons/lucide.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        return self::$lucide = is_array($data['icons'] ?? null) ? $data['icons'] : [];
    }

    /**
     * Settings for the admin script: where the sets are, cache-busted by plugin version.
     *
     * @return array{url: string, version: string}
     */
    public static function clientConfig(): array
    {
        return [
            'url'     => (defined('CTRLFIELD_URL') ? CTRLFIELD_URL : '') . 'assets/icons/',
            'version' => defined('CTRLFIELD_VERSION') ? (string) CTRLFIELD_VERSION : '',
        ];
    }
}
