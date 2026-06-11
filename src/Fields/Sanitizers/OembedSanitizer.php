<?php

declare(strict_types=1);

namespace FieldForge\Fields\Sanitizers;

use FieldForge\Fields\Contracts\SanitizerInterface;

final class OembedSanitizer implements SanitizerInterface
{
    /** Returns the sanitized URL string, or empty string for non-URL input. */
    public function sanitize(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            return esc_url_raw(trim($value));
        }

        $filtered = filter_var(trim($value), FILTER_SANITIZE_URL);
        return is_string($filtered) ? $filtered : '';
    }
}
