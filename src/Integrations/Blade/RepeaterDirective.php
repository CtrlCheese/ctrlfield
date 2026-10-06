<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

/**
 * Handles @repeater / @endrepeater Blade directives.
 *
 * Usage:
 *   @repeater('schedule')
 *       <li>{{ $phase_name }} — {{ $due_date }}</li>
 *   @endrepeater
 *
 * Each row's keys are extract()-ed into the local scope so variables are
 * available directly by name. EXTR_OVERWRITE is used intentionally: the row
 * data always wins over any pre-existing variable of the same name.
 */
final class RepeaterDirective
{
    /**
     * Compiles @repeater('key') into a foreach that extract()s each row.
     */
    public static function compile(string $expression): string
    {
        return sprintf(
            '<?php $__ctrlf_rows = (array)(ctrlfield_get(%s) ?? []); ' .
            'foreach ($__ctrlf_rows as $__ctrlf_row) { extract($__ctrlf_row, EXTR_OVERWRITE); ?>',
            $expression
        );
    }

    /**
     * Compiles @endrepeater — closes the foreach and cleans up internal variables.
     */
    public static function compileEnd(): string
    {
        return '<?php } unset($__ctrlf_rows, $__ctrlf_row); ?>';
    }
}
