<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TrueFalseField;

/**
 * Renders a boolean toggle switch for the TrueFalseField.
 * Value is stored as integer 1 (on) or 0 (off).
 * Alpine manages state: clicking the button flips adminState[key] between 1 and 0.
 */
final class TrueFalseRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $trueLabel  = $field instanceof TrueFalseField ? $field->getTrueLabel()  : 'Yes';
        $falseLabel = $field instanceof TrueFalseField ? $field->getFalseLabel() : 'No';
        $message    = $field instanceof TrueFalseField ? $field->getMessage()    : '';
        $p          = $this->esc($statePath);

        // Two square choices, like the button group: what it means is written, not drawn.
        $choice = static fn (string $label, string $value, string $active): string => sprintf(
            '<button type="button" role="radio" class="ctrlf-btn-group-item" :class="{\'is-active\': %3$s}"'
            . ' :aria-checked="(%3$s) ? \'true\' : \'false\'" @click="%1$s = %2$s">%4$s</button>',
            $p,
            $value,
            $active,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );

        return '<div class="ctrlf-truefalse">'
            . '<div class="ctrlf-button-group" role="radiogroup" id="' . $this->esc($this->inputId($field)) . '">'
            . $choice($falseLabel, '0', "!{$p}")
            . $choice($trueLabel, '1', "!!{$p}")
            . '</div>'
            . ($message !== '' ? '<span class="ctrlf-truefalse__message">' . $this->esc($message) . '</span>' : '')
            . '</div>';
    }
}
