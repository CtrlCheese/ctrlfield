<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Fields\Contracts\SanitizerInterface;

final class DateTimeSanitizer implements SanitizerInterface
{
    /** Returns the validated ISO 8601 datetime string, or null for invalid/empty input. */
    public function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);

        if ($dt === false || $dt->format('Y-m-d\TH:i:s') !== $value) {
            // Also accept the browser's datetime-local format without seconds
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $value);
            if ($dt === false) {
                return null;
            }
            return $dt->format('Y-m-d\TH:i:s');
        }

        return $value;
    }
}
