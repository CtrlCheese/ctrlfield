<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\NumberField;

final class NumberRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $extra = '';
        if ($field instanceof NumberField) {
            foreach (['min' => $field->getMin(), 'max' => $field->getMax(), 'step' => $field->getStep() ?? 'any'] as $attr => $value) {
                if ($value !== null) {
                    $extra .= ' ' . $attr . '="' . $this->esc((string) $value) . '"';
                }
            }
        }

        return $this->textInput($field, $statePath, 'number', 'x-model.number', $extra . ' inputmode="decimal"');
    }
}
