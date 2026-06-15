<?php

declare(strict_types=1);

namespace FieldForge\Components;

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
 *   'fieldforge/component_registered'  — per component, args: ($key, $definition)
 *   'fieldforge/components_loaded'     — once after all components are registered
 *
 * Core never hard-depends on Pro classes. FlexLayout (FieldForgePro\Fields\FlexLayout)
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
     */
    public static function discover(string $basePath, string $fieldsFile = 'fields.php'): void
    {
        $basePath = rtrim($basePath, '/');

        foreach (glob($basePath . '/*', GLOB_ONLYDIR) ?: [] as $componentPath) {
            $fieldsFilePath = $componentPath . '/' . $fieldsFile;

            if (! file_exists($fieldsFilePath)) {
                continue;
            }

            $key    = strtolower(basename($componentPath));
            $layout = self::loadFieldsFile($fieldsFilePath, $key);

            $label    = '';
            $icon     = '';
            $category = 'general';

            if (
                $layout !== null
                && class_exists(\FieldForgePro\Fields\FlexLayout::class)
                && $layout instanceof \FieldForgePro\Fields\FlexLayout
            ) {
                $label    = $layout->getLabel() ?: '';
                $icon     = $layout->getIcon() ?: '';
                $category = $layout->getCategory() ?: 'general';
            }

            if ($label === '') {
                $label = self::labelFromKey($key);
            }

            $definition = new ComponentDefinition(
                key:          $key,
                path:         $componentPath,
                label:        $label,
                icon:         $icon,
                category:     $category,
                templatePath: self::resolveTemplate($componentPath),
                hasScript:    file_exists($componentPath . '/script.js'),
                hasFunctions: file_exists($componentPath . '/functions.php'),
            );

            self::$components[$key] = $definition;

            if ($definition->hasFunctions) {
                require_once $componentPath . '/functions.php';
            }

            if (function_exists('do_action')) {
                do_action('fieldforge/component_registered', $key, $definition);
            }
        }

        if (function_exists('do_action')) {
            do_action('fieldforge/components_loaded');
        }
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
     * Resolves the component's template path in priority order:
     * index.blade.php → index.twig → index.php → null
     */
    private static function resolveTemplate(string $componentPath): ?string
    {
        foreach (['index.blade.php', 'index.twig', 'index.php'] as $filename) {
            $full = $componentPath . '/' . $filename;
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
