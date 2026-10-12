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

        foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $componentPath) {
            $folder         = basename($componentPath);
            $fieldsFilePath = $componentPath . '/' . $fieldsFile;

            if (in_array($folder, $skip, true) || ! file_exists($fieldsFilePath)) {
                continue;
            }

            $key    = self::keyFor($folder, $options['key'] ?? 'lower');
            $layout = self::loadFieldsFile($fieldsFilePath, $key);

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
                templatePath: self::resolveTemplate($componentPath, $templates, $folder, $key),
                hasScript:    file_exists($componentPath . '/script.js'),
                hasFunctions: file_exists($componentPath . '/functions.php'),
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
     * Resets the registry. Intended for use in tests only.
     */
    public static function reset(): void
    {
        self::$components = [];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Requires $path and returns its return value.
     * Returns null on any error so a broken fields.php never aborts discovery.
     */
    private static function loadFieldsFile(string $path, string $key): mixed
    {
        try {
            return require $path;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The component's template: the first of $names that exists ({folder} and
     * {key} replaced), by default index.blade.php → index.twig → index.php.
     *
     * @param list<string> $names
     */
    private static function resolveTemplate(string $componentPath, array $names, string $folder, string $key): ?string
    {
        foreach ($names as $name) {
            $full = $componentPath . '/' . str_replace(['{folder}', '{key}'], [$folder, $key], $name);
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
