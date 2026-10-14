<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\DateTimeField;

final class DateTimeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $format = $field instanceof DateTimeField ? $field->getFormat() : '';

        return DatePicker::render(
            $this->inputId($field),
            $statePath,
            'datetime',
            in_array($format, ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s'], true) ? '' : $format,
            $field instanceof DateTimeField ? $field->getMinDate() : null,
            $field instanceof DateTimeField ? $field->getMaxDate() : null,
            1,
            $field->isReadOnly(),
        );
    }
}
