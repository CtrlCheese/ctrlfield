<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\DateField;

final class DateRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $format = $field instanceof DateField ? $field->getFormat() : '';

        return DatePicker::render(
            $this->inputId($field),
            $statePath,
            'date',
            $format === 'Y-m-d' ? '' : $format, // the default: shown in the site's format
            $field instanceof DateField ? $field->getMinDate() : null,
            $field instanceof DateField ? $field->getMaxDate() : null,
            1,
            $field->isReadOnly(),
        );
    }
}
