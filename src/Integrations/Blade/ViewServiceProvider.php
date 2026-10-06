<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

use CtrlField\Bootstrap\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Bootstraps a standalone Blade compiler and registers CtrlField directives.
 *
 * Directive registry:
 *   @field('key')     — HTML-escaped field output
 *   @field_raw('key') — Unescaped field output (for WYSIWYG)
 *   @repeater('key')  — Iterates a repeater field, extract()-ing each row
 *   @endrepeater      — Closes the repeater loop
 *
 * Themes that use their own Blade engine can listen to:
 *   add_action('ctrlfield/blade_ready', function(BladeCompiler $compiler) { ... })
 * to receive the configured compiler and merge it with their own setup.
 */
class ViewServiceProvider extends ServiceProvider
{
    private static ?BladeCompiler $compiler = null;

    public function register(): void {}

    public function boot(): void
    {
        $compiler = $this->makeCompiler();

        $compiler->directive('field',      [FieldDirective::class,   'compile']);
        $compiler->directive('field_raw',  [FieldDirective::class,   'compileRaw']);
        $compiler->directive('repeater',   [RepeaterDirective::class, 'compile']);
        $compiler->directive('endrepeater', fn() => RepeaterDirective::compileEnd());

        // @ctrlfSection($section) — renders a flexible-content section array.
        // Usage: @ctrlfSection($section)
        $compiler->directive('ctrlfSection', static function (string $expression): string {
            return "<?php echo \\CtrlField\\Components\\ComponentRenderer::render({$expression}); ?>";
        });

        // @ctrlfComponent('key', $data) — renders a named component with explicit data.
        // Usage: @ctrlfComponent('hero', ['headline' => 'Welcome'])
        $compiler->directive('ctrlfComponent', static function (string $expression): string {
            // expression arrives without the outer parens, e.g.: 'hero', ['key' => 'val']
            // Split on the first comma to separate name from data argument.
            [$name, $data] = array_pad(
                array_map('trim', explode(',', $expression, 2)),
                2,
                '[]',
            );
            return "<?php echo \\CtrlField\\Components\\ComponentRenderer::renderByName({$name}, {$data}); ?>";
        });

        self::$compiler = $compiler;

        if (function_exists('do_action')) {
            do_action('ctrlfield/blade_ready', $compiler);
        }
    }

    /**
     * Returns the active compiler instance.
     * Useful for themes/plugins that want to add their own directives
     * after CtrlField has booted.
     */
    public static function compiler(): ?BladeCompiler
    {
        return self::$compiler;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function makeCompiler(): BladeCompiler
    {
        $cachePath = $this->resolveCachePath();

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        return new BladeCompiler(
            new Filesystem(),
            $cachePath,
            '',
            false, // do not require the cache directory to exist
        );
    }

    private function resolveCachePath(): string
    {
        if (defined('WP_CONTENT_DIR')) {
            return WP_CONTENT_DIR . '/cache/ctrlfield/blade';
        }

        return sys_get_temp_dir() . '/ctrlfield-blade';
    }
}
