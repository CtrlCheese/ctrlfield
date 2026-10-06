<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RangeField;

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
            '<input type="range" id="%s" x-model.number="%s" min="%s" max="%s" step="%s" class="ctrlf-input ctrlf-input--range"%s>',
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
            '<div class="ctrlf-range-wrap">%s<output class="ctrlf-range-output" x-text="%s"></output></div>',
            $input,
            $this->esc($statePath),
        );
    }
}
