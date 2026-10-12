<?php

declare(strict_types=1);

namespace CtrlField\Components;

/**
 * Singleton that discovers, tracks, and exposes registered components.
 *
 * Usage (theme's functions.php):
 *
 *   add_action('init', function () {
 *       ComponentRegistry::discover(get_template_directory() . '/components/');
 *   }, 5);
 *
 * Fires WordPress actions:
 *   'ctrlfield/component_registered'  — per component, args: ($key, $definition)
 *   'ctrlfield/components_loaded'     — once after all components are registered
 *
 * Core never hard-depends on Pro classes. FlexLayout (CtrlField\Pro\Fields\FlexLayout)
 * is referenced only behind class_exists() guards.
 */
final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private static array $components = [];

    /** @var array<string, mixed> key => what the fields file returned (loaded once per request) */
    private static array $loaded = [];

    /**
     * Scans $basePath for subdirectories that contain $fieldsFile.
     * Skips directories without a fields file.
     * Auto-loads functions.php immediately after registration.
     *
     * Options, for themes whose components already have their own conventions:
     *
     *   ComponentRegistry::discover(get_template_directory() . '/components', 'fields.php', [
     *       'key'          => 'snake',                       // ModuleTabs → module_tabs ('lower' default, 'kebab', or fn (string $folder): string)
     *       'templates'    => ['{folder}.blade.php'],        // template names, in order; {folder} / {key} are replaced
     *       'prefixFields' => true,                          // top-level field keys get "{key}_" (module_tabs_title)
     *       'skip'         => ['ModuleNavigation'],          // folders that are no component
     *       'load'         => fn (string $file, string $key, string $folder) => …,  // loads fields.php in the theme's context
     *       'scripts'      => false,                         // the theme loads components' script.js itself
     *       'fields'       => fn (array $fields, ComponentDefinition $c): array => …,   // shared fields, …
     *       'meta'         => fn (string $folder, string $path): array => […],         // label / category / description / icon
     *       'data'         => fn (array $section, ComponentDefinition $c): array => …,  // the theme's getData() before rendering
     *   ]);
     *
     * @param array{
     *     key?: 'lower'|'snake'|'kebab'|callable(string): string,
     *     templates?: list<string>,
     *     prefixFields?: bool,
     *     skip?: list<string>,
     *     load?: callable(string, string, string): mixed,
     *     scripts?: bool,
     *     fields?: callable(array<int, mixed>, ComponentDefinition): array<int, mixed>,
     *     meta?: callable(string, string): array<string, string>,
     *     data?: callable(array<string, mixed>, ComponentDefinition): array<string, mixed>,
     * } $options
     */
    public static function discover(string $basePath, string $fieldsFile = 'fields.php', array $options = []): void
    {
        $basePath  = rtrim($basePath, '/');
        $skip      = $options['skip'] ?? [];
        $templates = $options['templates'] ?? ['index.blade.php', 'index.twig', 'index.php'];
        $data      = isset($options['data']) ? \Closure::fromCallable($options['data']) : null;
        $fields    = isset($options['fields']) ? \Closure::fromCallable($options['fields']) : null;
        $load      = isset($options['load']) ? \Closure::fromCallable($options['load']) : null;
        $scripts   = ($options['scripts'] ?? true) !== false;

        foreach (self::scan($basePath, $fieldsFile, $templates) as $found) {
            ['folder' => $folder, 'templates' => $files] = $found;
            $componentPath  = $basePath . '/' . $folder;
            $fieldsFilePath = $componentPath . '/' . $fieldsFile;

            if (in_array($folder, $skip, true)) {
                continue;
            }

            $key    = self::keyFor($folder, $options['key'] ?? 'lower');
            $layout = self::loadFieldsFile($fieldsFilePath, $key, $folder, $load);
            self::$loaded[$key] = $layout;

            $label       = '';
            $icon        = '';
            $category    = 'general';
            $description = '';

            if (
                $layout !== null
                && class_exists(\CtrlField\Pro\Fields\FlexLayout::class)
                && $layout instanceof \CtrlField\Pro\Fields\FlexLayout
            ) {
                $label       = $layout->getLabel() ?: '';
                $icon        = $layout->getIcon() ?: '';
                $category    = $layout->getCategory() ?: 'general';
                $description = $layout->getDescription();
            }

            // The theme's own metadata (e.g. static $label on the component class) wins.
            $meta = isset($options['meta']) ? (array) ($options['meta'])($folder, $componentPath) : [];
            $label       = is_string($meta['label'] ?? null) && $meta['label'] !== '' ? $meta['label'] : $label;
            $icon        = is_string($meta['icon'] ?? null) && $meta['icon'] !== '' ? $meta['icon'] : $icon;
            $category    = is_string($meta['category'] ?? null) && $meta['category'] !== '' ? $meta['category'] : $category;
            $description = is_string($meta['description'] ?? null) && $meta['description'] !== '' ? $meta['description'] : $description;

            if ($label === '') {
                $label = self::labelFromKey($folder);
            }

            $definition = new ComponentDefinition(
                key:          $key,
                path:         $componentPath,
                label:        $label,
                icon:         $icon,
                category:     $category,
                templatePath: self::resolveTemplate($componentPath, $templates, $folder, $key, $files),
                hasScript:    $scripts && in_array('script.js', $files, true),
                hasFunctions: in_array('functions.php', $files, true),
                folder:       $folder,
                fieldsFile:   $fieldsFilePath,
                fieldPrefix:  ! empty($options['prefixFields']) ? $key . '_' : '',
                description:  $description,
                data:         $data,
                fields:       $fields,
            );

            self::$components[$key] = $definition;

            if ($definition->hasFunctions) {
                require_once $componentPath . '/functions.php';
            }

            if (function_exists('do_action')) {
                do_action('ctrlfield/component_registered', $key, $definition);
            }
        }

        if (function_exists('do_action')) {
            do_action('ctrlfield/components_loaded');
        }
    }

    /**
     * The layout key of a component folder.
     *
     *   'lower' ModuleTabs → moduletabs   'snake' → module_tabs   'kebab' → module-tabs
     *
     * @param string|callable(string): string $format
     */
    public static function keyFor(string $folder, string|callable $format = 'lower'): string
    {
        if (! is_string($format)) {
            return (string) $format($folder);
        }
        $words = static fn (string $sep): string => strtolower(trim((string) preg_replace(
            ['/([a-z0-9])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/', '/[\s_-]+/'],
            ['$1' . $sep . '$2', '$1' . $sep . '$2', $sep],
            $folder
        ), $sep));

        return match ($format) {
            'snake' => $words('_'),
            'kebab' => $words('-'),
            default => strtolower($folder),
        };
    }

    /**
     * Returns the ComponentDefinition for $key, or null if not registered.
     */
    public static function get(string $key): ?ComponentDefinition
    {
        return self::$components[$key] ?? null;
    }

    /**
     * Returns true if a component with the given key is registered.
     */
    public static function has(string $key): bool
    {
        return isset(self::$components[$key]);
    }

    /**
     * Returns all registered components indexed by key.
     *
     * @return array<string, ComponentDefinition>
     */
    public static function all(): array
    {
        return self::$components;
    }

    /**
     * Returns the absolute directory path of a registered component, or null.
     */
    public static function getPath(string $key): ?string
    {
        return self::$components[$key]?->path;
    }

    /**
     * Returns the resolved template path of a registered component, or null.
     */
    public static function getTemplatePath(string $key): ?string
    {
        return self::$components[$key]?->templatePath;
    }

    /**
     * What a component's fields file returned when it was discovered
     * (null when it failed or returned nothing) — ->autoDiscover() builds the
     * layout from it instead of loading the file a second time.
     */
    public static function loadedFields(string $key): mixed
    {
        return self::$loaded[$key] ?? null;
    }

    /**
     * Resets the registry. Intended for use in tests only.
     */
    public static function reset(): void
    {
        self::$components = [];
        self::$loaded     = [];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Requires $path (or hands it to the theme's 'load' callback) and returns
     * its return value. A broken fields.php never aborts discovery: the error
     * is shown to admins (SchemaErrors) and the component has no fields.
     */
    private static function loadFieldsFile(string $path, string $key, string $folder = '', ?\Closure $load = null): mixed
    {
        try {
            return $load !== null ? $load($path, $key, $folder) : require $path;
        } catch (\Throwable $e) {
            if (class_exists(\CtrlField\Schema\SchemaErrors::class)) {
                \CtrlField\Schema\SchemaErrors::add($path, $e);
            }
            return null;
        }
    }

    /**
     * The component folders (those with a fields file) and the files each
     * one has that discovery looks at. Cached until the components folder or
     * one of its folders changes; not cached while WP_DEBUG is on.
     *
     * @param  list<string> $templates
     * @return list<array{folder: string, templates: list<string>}>
     */
    private static function scan(string $basePath, string $fieldsFile, array $templates): array
    {
        $dirs = glob($basePath . '/*', GLOB_ONLYDIR) ?: [];

        $cacheKey = null;
        $useCache = function_exists('get_transient') && ! (defined('WP_DEBUG') && WP_DEBUG);
        if ($useCache) {
            $stamp = (string) @filemtime($basePath);
            foreach ($dirs as $dir) {
                $stamp .= '|' . @filemtime($dir);
            }
            $cacheKey = 'ctrlfield_components_' . md5($basePath . '|' . $fieldsFile . '|' . implode(',', $templates) . '|' . $stamp);
            $cached   = get_transient($cacheKey);
            if (is_array($cached) && array_is_list($cached)) {
                /** @var list<array{folder: string, templates: list<string>}> $cached */
                return $cached;
            }
        }

        $found = [];
        foreach ($dirs as $dir) {
            if (! file_exists($dir . '/' . $fieldsFile)) {
                continue;
            }
            $folder = basename($dir);
            $files  = [];
            foreach (['script.js', 'functions.php', ...$templates] as $name) {
                $name = str_replace('{folder}', $folder, $name);
                if (! str_contains($name, '{key}') && file_exists($dir . '/' . $name)) {
                    $files[] = $name;
                }
            }
            $found[] = ['folder' => $folder, 'templates' => $files];
        }

        if ($cacheKey !== null) {
            set_transient($cacheKey, $found, DAY_IN_SECONDS);
        }

        return $found;
    }

    /**
     * The component's template: the first of $names that exists ({folder} and
     * {key} replaced), by default index.blade.php → index.twig → index.php.
     *
     * @param list<string> $names
     * @param list<string> $known files found by scan() ({folder} already replaced)
     */
    private static function resolveTemplate(string $componentPath, array $names, string $folder, string $key, array $known = []): ?string
    {
        foreach ($names as $name) {
            $file = str_replace(['{folder}', '{key}'], [$folder, $key], $name);
            if (in_array($file, $known, true)) {
                return $componentPath . '/' . $file;
            }
            $full = $componentPath . '/' . $file;
            if (file_exists($full)) {
                return $full;
            }
        }

        return null;
    }

    /**
     * Derives a human-readable label from a component key.
     *
     * Handles camelCase, PascalCase, snake_case, and kebab-case:
     *   'heroSection'  → 'Hero Section'
     *   'hero_section' → 'Hero Section'
     *   'HeroBanner'   → 'Hero Banner'
     *   'hero-banner'  → 'Hero Banner'
     */
    private static function labelFromKey(string $key): string
    {
        // Insert a space before each uppercase letter (camelCase / PascalCase)
        $spaced = (string) preg_replace('/([A-Z])/', ' $1', $key);
        // Replace separators
        $spaced = str_replace(['-', '_'], ' ', $spaced);

        return ucwords(strtolower(trim($spaced)));
    }
}
