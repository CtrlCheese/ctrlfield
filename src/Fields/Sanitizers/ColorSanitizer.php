<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Fields\Contracts\SanitizerInterface;

final class ColorSanitizer implements SanitizerInterface
{
    /**
     * Returns the hex color with a leading "#", as typed (case kept — themes
     * compare and print it, and the color picker gives lowercase), or null
     * for invalid/empty input.
     */
    public function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $hex = trim($value);

        if (! str_starts_with($hex, '#')) {
            $hex = '#' . $hex;
        }

        // Accept #RGB (3), #RRGGBB (6), #RRGGBBAA (8)
        if (! preg_match('/^#(?:[0-9A-F]{3}|[0-9A-F]{6}|[0-9A-F]{8})$/i', $hex)) {
            return null;
        }

        return $hex;
    }
}
