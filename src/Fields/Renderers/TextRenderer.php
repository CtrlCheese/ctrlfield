<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class TextRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="text" id="%s" x-model="%s" class="ctrlf-input ctrlf-input--text">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
