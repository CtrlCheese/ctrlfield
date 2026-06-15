<?php

declare(strict_types=1);

namespace FieldForge\Components;

/**
 * Renders a component by resolving its template and applying the data filter pipeline.
 *
 * Supports three template engines in priority order (resolved at discovery time):
 *   1. Blade  (.blade.php) — via ViewServiceProvider's BladeCompiler, falls back to PHP
 *   2. Twig   (.twig)      — via Timber\Timber::compile(), falls back to PHP
 *   3. PHP    (.php)       — plain include with extract()
 *
 * WordPress filter hooks applied before rendering:
 *   'fieldforge/component_data/{layout_key}'  — per-component data transform
 *   'fieldforge/component_data'               — global data transform (args: $data, $key)
 *
 * WordPress filter hooks applied after rendering:
 *   'fieldforge/component_render/{layout_key}' — post-render HTML transform (args: $html, $data)
 *
 * All public methods are safe: they return '' instead of throwing when a
 * component or template is missing or when rendering fails.
 */
final class ComponentRenderer
{
    /**
     * Renders a flexible-content section array.
     *
     * $section must contain a '_layout' key that matches a registered component key.
     *
     * @param array<string, mixed> $section
     */
    public static function render(array $section): string
    {
        $layoutKey = (string) ($section['_layout'] ?? '');

        if ($layoutKey === '') {
            return '';
        }

        // Apply per-component data filters
        if (function_exists('apply_filters')) {
            /** @var array<string, mixed> $section */
            $section = apply_filters("fieldforge/component_data/{$layoutKey}", $section);
            /** @var array<string, mixed> $section */
            $section = apply_filters('fieldforge/component_data', $section, $layoutKey);
        }

        $definition = ComponentRegistry::get($layoutKey);

        if ($definition === null || $definition->templatePath === null) {
            $html = '';
            if (function_exists('apply_filters')) {
                /** @var string $html */
                $html = apply_filters("fieldforge/component_render/{$layoutKey}", $html, $section);
            }
            return $html;
        }

        $html = self::renderTemplate($definition->templatePath, $section);

        if (function_exists('apply_filters')) {
            /** @var string $html */
            $html = apply_filters("fieldforge/component_render/{$layoutKey}", $html, $section);
        }

        return $html;
    }

    /**
     * Renders a component by name with explicit data.
     *
     * Injects '_layout' into $data and delegates to render().
     * Useful for @ffComponent and ff_render() where no section array is available.
     *
     * @param array<string, mixed> $data
     */
    public static function renderByName(string $name, array $data = []): string
    {
        $data['_layout'] = $name;
        return self::render($data);
    }

    // -------------------------------------------------------------------------
    // Private rendering helpers
    // -------------------------------------------------------------------------

    /**
     * Dispatches to the appropriate engine based on file extension.
     *
     * @param array<string, mixed> $data
     */
    private static function renderTemplate(string $path, array $data): string
    {
        return match (true) {
            str_ends_with($path, '.blade.php') => self::renderBlade($path, $data),
            str_ends_with($path, '.twig')      => self::renderTwig($path, $data),
            default                             => self::renderPhp($path, $data),
        };
    }

    /**
     * Renders a Blade template.
     *
     * Uses the BladeCompiler registered by ViewServiceProvider when available.
     * Falls back to plain PHP rendering if Blade is not set up.
     *
     * @param array<string, mixed> $data
     */
    private static function renderBlade(string $path, array $data): string
    {
        if (class_exists(\FieldForge\Integrations\Blade\ViewServiceProvider::class)) {
            $compiler = \FieldForge\Integrations\Blade\ViewServiceProvider::compiler();

            if ($compiler !== null) {
                try {
                    $compiled = $compiler->getCompiledPath($path);

                    if (! $compiler->isExpired($path)) {
                        // Already compiled — just render
                        return self::renderCompiledBlade($compiled, $data);
                    }

                    // Compile then render
                    $compiler->compile($path);
                    return self::renderCompiledBlade($compiled, $data);
                } catch (\Throwable) {
                    // Fall through to PHP rendering
                }
            }
        }

        return self::renderPhp($path, $data);
    }

    /**
     * Includes a compiled Blade file with extracted data and captures output.
     *
     * @param array<string, mixed> $__data
     */
    private static function renderCompiledBlade(string $__path, array $__data): string
    {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__path;
        } catch (\Throwable) {
            ob_end_clean();
            return '';
        }
        return (string) ob_get_clean();
    }

    /**
     * Renders a Twig template via Timber.
     *
     * Falls back to plain PHP if Timber is not installed.
     *
     * @param array<string, mixed> $data
     */
    private static function renderTwig(string $path, array $data): string
    {
        if (class_exists(\Timber\Timber::class)) {
            try {
                $result = \Timber\Timber::compile($path, $data);
                return is_string($result) ? $result : '';
            } catch (\Throwable) {
                // Fall through to PHP rendering
            }
        }

        return self::renderPhp($path, $data);
    }

    /**
     * Renders a plain PHP template with extract()-ed data.
     *
     * @param array<string, mixed> $data
     */
    private static function renderPhp(string $path, array $data): string
    {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $path;
        } catch (\Throwable) {
            ob_end_clean();
            return '';
        }
        return (string) ob_get_clean();
    }
}
