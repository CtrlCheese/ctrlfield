<?php

declare(strict_types=1);

namespace FieldForge\Integrations\Blade;

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
            '<?php $__ff_rows = (array)(fieldforge_get(%s) ?? []); ' .
            'foreach ($__ff_rows as $__ff_row) { extract($__ff_row, EXTR_OVERWRITE); ?>',
            $expression
        );
    }

    /**
     * Compiles @endrepeater — closes the foreach and cleans up internal variables.
     */
    public static function compileEnd(): string
    {
        return '<?php } unset($__ff_rows, $__ff_row); ?>';
    }
}
