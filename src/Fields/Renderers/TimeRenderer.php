<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\TimeField;

final class TimeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $stepMinutes = $field instanceof TimeField ? $field->getStep() : 1;
        $stepSeconds = $stepMinutes * 60;
        $readOnly    = $field->isReadOnly();
        $roAttr      = $readOnly ? ' disabled' : '';

        return sprintf(
            '<input type="time" id="%s" x-model="%s" step="%d" class="ff-input ff-input--time"%s>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $stepSeconds,
            $roAttr,
        );
    }
}
