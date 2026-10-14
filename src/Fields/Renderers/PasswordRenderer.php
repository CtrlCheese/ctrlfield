<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class PasswordRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return $this->textInput($field, $statePath, 'password', 'x-model', ' autocomplete="new-password"');
    }
}
