<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

/**
 * Handles @field and @field_raw Blade directives.
 *
 * @field('key')     — HTML-escaped output. Safe for all field types.
 * @field_raw('key') — Unescaped output. Use only for trusted WYSIWYG content.
 */
final class FieldDirective
{
    /**
     * Compiles @field('key') into escaped PHP output.
     *
     * @param string $expression The raw Blade expression (e.g. 'client_name' or $variable).
     */
    public static function compile(string $expression): string
    {
        return sprintf(
            '<?php echo htmlspecialchars((string)(ctrlfield_get(%s) ?? \'\'), ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\'); ?>',
            $expression
        );
    }

    /**
     * Compiles @field_raw('key') into unescaped PHP output.
     *
     * Only use for content you trust (e.g. WYSIWYG saved by an authenticated editor).
     */
    public static function compileRaw(string $expression): string
    {
        return sprintf(
            '<?php echo ctrlfield_get(%s) ?? \'\'; ?>',
            $expression
        );
    }
}
