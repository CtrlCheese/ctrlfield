<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class TextareaRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<textarea id="%s" x-model="%s" class="ctrlf-input ctrlf-input--textarea" rows="5"></textarea>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
