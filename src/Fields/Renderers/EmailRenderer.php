<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class EmailRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="email" id="%s" x-model="%s" class="ff-input ff-input--email">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
