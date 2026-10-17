<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

/**
 * The values of a repeater / flexible row as template variables (@repeater,
 * @flexible extract() them). Only names a field key can have: a stored row
 * cannot replace Blade's own $__env / $__data, the loop's $_ctrlf_* or $this.
 */
final class RowVars
{
    /** @return array<string, mixed> */
    public static function of(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        return array_filter(
            $row,
            static fn ($key): bool => is_string($key)
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1
                && ! str_starts_with($key, '__')
                && ! str_starts_with($key, '_ctrlf')
                && ! in_array($key, ['this', 'GLOBALS'], true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
