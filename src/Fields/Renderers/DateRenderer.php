<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\DateField;

final class DateRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $min      = $field instanceof DateField ? $field->getMinDate() : null;
        $max      = $field instanceof DateField ? $field->getMaxDate() : null;
        $readOnly = $field->isReadOnly();

        $minAttr = $min !== null ? sprintf(' min="%s"', $this->esc($min)) : '';
        $maxAttr = $max !== null ? sprintf(' max="%s"', $this->esc($max)) : '';
        $roAttr  = $readOnly ? ' disabled' : '';

        return sprintf(
            '<input type="date" id="%s" x-model="%s" class="ff-input ff-input--date"%s%s%s>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $minAttr,
            $maxAttr,
            $roAttr,
        );
    }
}
