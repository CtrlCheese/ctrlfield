<?php

declare(strict_types=1);

namespace FieldForge\Fields\Sanitizers;

use FieldForge\Fields\Contracts\SanitizerInterface;

final class TimeSanitizer implements SanitizerInterface
{
    /** Returns the validated HH:MM string, or null for invalid/empty input. */
    public function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $value)) {
            return null;
        }

        [$h, $m] = explode(':', $value);

        if ((int) $h > 23 || (int) $m > 59) {
            return null;
        }

        return $value;
    }
}
