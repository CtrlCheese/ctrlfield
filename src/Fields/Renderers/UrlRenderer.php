<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class UrlRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="url" id="%s" x-model="%s" class="ff-input ff-input--url">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
