<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class TextareaRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<textarea id="%s" x-model="%s" class="ff-input ff-input--textarea" rows="5"></textarea>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
