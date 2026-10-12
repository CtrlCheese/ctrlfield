<?php

declare(strict_types=1);

namespace CtrlField\Data;

/**
 * The icon sets of the Icon field and the HTML of one icon.
 *
 * Built in: Lucide and Dashicons (assets/icons/*.json, bin/build-icons.mjs).
 * Values: "lucide:arrow-right", "dashicons-admin-site", "{set}:{name}" for a
 * set a theme adds. SVG icons are printed inline, so the site loads nothing extra.
 *
 * A theme adds a set (e.g. the brand icons it uses) as one more tab of the
 * icon dialog:
 *
 *   add_filter('ctrlfield/icon_sets', fn (array $sets) => $sets + ['brand' => [
 *       'label' => 'Brands',
 *       'file'  => get_theme_file_path('icons/brands.json'),   // read by PHP
 *       'url'   => get_theme_file_uri('icons/brands.json'),    // fetched by the admin
 *       'attrs' => ['fill' => 'currentColor'],                 // <svg> attributes (default: filled, viewBox 0 0 24 24)
 *   ]]);
 *
 * brands.json: { "icons": { "facebook": "<path d=…/>", "x": { "body": "<path …/>", "viewBox": "0 0 512 512" } },
 *                "tags": { "facebook": "social meta" } }
 */
final class IconLibrary
{
    /** The built-in libraries, in tab order. */
    public const LIBRARIES = ['lucide', 'dashicons'];

    private const NAME = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Lucide's line style. */
    private const LUCIDE_ATTRS = [
        'fill'            => 'none',
        'stroke'          => 'currentColor',
        'stroke-width'    => 2,
        'stroke-linecap'  => 'round',
        'stroke-linejoin' => 'round',
    ];

    /** @var array<string, array{icons: array<string, mixed>, aliases: array<string, string>}> set => loaded JSON */
    private static array $loaded = [];

    /** @var array<string, array{label: string, file: string, url: string, attrs: array<string, scalar>}>|null */
    private static ?array $sets = null;

    /**
     * The SVG sets: Lucide plus those added with the ctrlfield/icon_sets filter.
     *
     * @return array<string, array{label: string, file: string, url: string, attrs: array<string, scalar>}>
     */
    public static function svgSets(): array
    {
        if (self::$sets !== null) {
            return self::$sets;
        }
        $base = dirname(__DIR__, 2) . '/assets/icons/';
        $url  = (defined('CTRLFIELD_URL') ? CTRLFIELD_URL : '') . 'assets/icons/';
        $sets = ['lucide' => ['label' => 'Lucide', 'file' => $base . 'lucide.json', 'url' => $url . 'lucide.json', 'attrs' => self::LUCIDE_ATTRS]];

        $extra = function_exists('apply_filters') ? apply_filters('ctrlfield/icon_sets', []) : [];
        foreach (is_array($extra) ? $extra : [] as $id => $set) {
            if (! is_string($id) || ! preg_match('/^[a-z][a-z0-9]{0,19}$/', $id) || isset($sets[$id]) || $id === 'dashicons' || ! is_array($set)) {
                continue;
            }
            $sets[$id] = [
                'label' => is_string($set['label'] ?? null) ? $set['label'] : ucfirst($id),
                'file'  => is_string($set['file'] ?? null) ? $set['file'] : '',
                'url'   => is_string($set['url'] ?? null) ? $set['url'] : '',
                'attrs' => array_filter(is_array($set['attrs'] ?? null) ? $set['attrs'] : ['fill' => 'currentColor'], 'is_scalar'),
            ];
        }

        return self::$sets = $sets;
    }

    /** @return list<string> Every library id, in tab order (Lucide, added sets, Dashicons). */
    public static function libraries(): array
    {
        return [...array_keys(self::svgSets()), 'dashicons'];
    }

    /**
     * "lucide:arrow-right" → ['lucide', 'arrow-right']; ['', ''] when not an icon value.
     *
     * @return array{0: string, 1: string}
     */
    public static function parse(mixed $value): array
    {
        $v = is_string($value) ? trim($value) : '';
        if (str_starts_with($v, 'dashicons-') && preg_match(self::NAME, substr($v, 10))) {
            return ['dashicons', substr($v, 10)];
        }
        $colon = strpos($v, ':');
        if ($colon !== false) {
            $set  = substr($v, 0, $colon);
            $name = substr($v, $colon + 1);
            if (isset(self::svgSets()[$set]) && preg_match(self::NAME, $name)) {
                return [$set, $name];
            }
        }
        return ['', ''];
    }

    /** Whether the value can be stored for an Icon field (sanitizer). */
    public static function isValid(mixed $value): bool
    {
        return self::parse($value)[0] !== '';
    }

    /**
     * The icon as HTML: a Dashicons span, or the SVG inline.
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
        if ($lib === '') {
            return '';
        }

        $set   = self::load($lib);
        $icon  = $set['icons'][$name] ?? $set['icons'][$set['aliases'][$name] ?? ''] ?? null;
        $body  = is_array($icon) ? ($icon['body'] ?? null) : $icon;
        if (! is_string($body) || $body === '') {
            return '';
        }
        $viewBox = is_array($icon) && is_string($icon['viewBox'] ?? null) ? $icon['viewBox'] : '0 0 24 24';

        $attrs += ['xmlns' => 'http://www.w3.org/2000/svg', 'width' => 24, 'height' => 24, 'viewBox' => $viewBox]
            + self::svgSets()[$lib]['attrs'];
        $attrs['class'] = trim($lib . ' ' . $lib . '-' . $name . ' ' . (string) ($attrs['class'] ?? ''));

        $out = '<svg';
        foreach ($attrs as $key => $val) {
            $out .= ' ' . preg_replace('/[^a-zA-Z0-9:_-]/', '', (string) $key) . '="' . esc_attr((string) $val) . '"';
        }
        // The body comes from the set's JSON (the plugin's build or the theme's file), not from user input.
        return $out . $a11y . ' focusable="false">' . $body . '</svg>';
    }

    /** @return array{icons: array<string, mixed>, aliases: array<string, string>} */
    private static function load(string $set): array
    {
        if (isset(self::$loaded[$set])) {
            return self::$loaded[$set];
        }
        $file = self::svgSets()[$set]['file'] ?? '';
        $data = $file !== '' && is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        return self::$loaded[$set] = [
            'icons'   => is_array($data['icons'] ?? null) ? $data['icons'] : [],
            'aliases' => is_array($data['aliases'] ?? null) ? $data['aliases'] : [],
        ];
    }

    /**
     * Settings for the admin script: where the sets are, cache-busted by plugin version.
     *
     * @return array{url: string, version: string, sets: list<array{id: string, label: string, url: string, attrs: array<string, scalar>}>}
     */
    public static function clientConfig(): array
    {
        $sets = [];
        foreach (self::svgSets() as $id => $set) {
            $sets[] = ['id' => $id, 'label' => $set['label'], 'url' => $set['url'], 'attrs' => $set['attrs']];
        }

        return [
            'url'     => (defined('CTRLFIELD_URL') ? CTRLFIELD_URL : '') . 'assets/icons/',
            'version' => defined('CTRLFIELD_VERSION') ? (string) CTRLFIELD_VERSION : '',
            'sets'    => $sets,
        ];
    }

    /** Forgets the sets and the loaded files (tests). */
    public static function reset(): void
    {
        self::$sets   = null;
        self::$loaded = [];
    }
}
