<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class TextRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="text" id="%s" x-model="%s" class="ff-input ff-input--text">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
