<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class NumberRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="number" id="%s" x-model.number="%s" class="ctrlf-input ctrlf-input--number">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
