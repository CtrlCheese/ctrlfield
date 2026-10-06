<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TimeField;

final class TimeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $stepMinutes = $field instanceof TimeField ? $field->getStep() : 1;
        $stepSeconds = $stepMinutes * 60;
        $readOnly    = $field->isReadOnly();
        $roAttr      = $readOnly ? ' disabled' : '';

        return sprintf(
            '<input type="time" id="%s" x-model="%s" step="%d" class="ctrlf-input ctrlf-input--time"%s>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $stepSeconds,
            $roAttr,
        );
    }
}
