<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TimeField;

final class TimeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return DatePicker::render(
            $this->inputId($field),
            $statePath,
            'time',
            '',
            null,
            null,
            $field instanceof TimeField ? $field->getStep() : 1,
            $field->isReadOnly(),
        );
    }
}
