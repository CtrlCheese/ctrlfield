<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\DateTimeField;

final class DateTimeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $min      = $field instanceof DateTimeField ? $field->getMinDate() : null;
        $max      = $field instanceof DateTimeField ? $field->getMaxDate() : null;
        $readOnly = $field->isReadOnly();

        // datetime-local expects 'YYYY-MM-DDTHH:MM', strip seconds from min/max
        $minAttr = $min !== null ? sprintf(' min="%s"', $this->esc(substr($min, 0, 16))) : '';
        $maxAttr = $max !== null ? sprintf(' max="%s"', $this->esc(substr($max, 0, 16))) : '';
        $roAttr  = $readOnly ? ' disabled' : '';

        return sprintf(
            '<input type="datetime-local" id="%s" x-model="%s" class="ctrlf-input ctrlf-input--datetime"%s%s%s>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $minAttr,
            $maxAttr,
            $roAttr,
        );
    }
}
