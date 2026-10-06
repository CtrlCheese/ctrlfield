<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Fields\Contracts\SanitizerInterface;

final class RangeSanitizer implements SanitizerInterface
{
    /** Returns the value cast to float. Clamping is handled by TypeCoercionStage. */
    public function sanitize(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
