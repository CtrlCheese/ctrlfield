<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TextareaField;

final class TextareaRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $rows = $field instanceof TextareaField ? $field->getRows() : 5;

        return $this->textInput($field, $statePath, 'textarea', 'x-model', ' rows="' . $rows . '"');
    }
}
