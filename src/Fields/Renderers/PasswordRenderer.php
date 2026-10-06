<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class PasswordRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return sprintf(
            '<input type="password" id="%s" x-model="%s" autocomplete="new-password" class="ctrlf-input ctrlf-input--password">',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
        );
    }
}
