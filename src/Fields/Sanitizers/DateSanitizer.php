<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Fields\Contracts\SanitizerInterface;

final class DateSanitizer implements SanitizerInterface
{
    /** Returns the validated ISO 8601 date string, or null for invalid/empty input. */
    public function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $value);

        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
