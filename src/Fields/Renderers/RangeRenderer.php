<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\RangeField;

final class RangeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $min       = $field instanceof RangeField ? $field->getMin() : 0.0;
        $max       = $field instanceof RangeField ? $field->getMax() : 100.0;
        $step      = $field instanceof RangeField ? $field->getStep() : 1.0;
        $showValue = $field instanceof RangeField && $field->isShowValue();
        $readOnly  = $field->isReadOnly();
        $roAttr    = $readOnly ? ' disabled' : '';
        $id        = $this->esc($this->inputId($field));

        $input = sprintf(
            '<input type="range" id="%s" x-model.number="%s" min="%s" max="%s" step="%s" class="ff-input ff-input--range"%s>',
            $id,
            $this->esc($statePath),
            $this->esc((string) $min),
            $this->esc((string) $max),
            $this->esc((string) $step),
            $roAttr,
        );

        if (! $showValue) {
            return $input;
        }

        return sprintf(
            '<div class="ff-range-wrap">%s<output class="ff-range-output" x-text="%s"></output></div>',
            $input,
            $this->esc($statePath),
        );
    }
}
