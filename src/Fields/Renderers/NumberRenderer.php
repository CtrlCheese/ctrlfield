<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class NumberRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="number" id="%s" x-model.number="%s" class="ff-input ff-input--number">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
