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
        $id         = $this->esc($this->inputId($field));

        $messageHtml = $message !== ''
            ? sprintf(' <span class="ctrlf-toggle-message">%s</span>', $this->esc($message))
            : '';

        return sprintf(
            '<div class="ctrlf-toggle-wrap">'
            . '<span class="ctrlf-toggle-label-off" :class="{\'ctrlf-toggle-inactive\': %2$s}">%3$s</span>'
            . '<button type="button" id="%1$s" role="switch"'
            . ' :aria-checked="%2$s ? \'true\' : \'false\'"'
            . ' @click="%2$s = %2$s ? 0 : 1"'
            . ' :class="{\'is-on\': %2$s}"'
            . ' class="ctrlf-toggle-button">'
            . '<span class="ctrlf-toggle-thumb"></span>'
            . '</button>'
            . '<span class="ctrlf-toggle-label-on" :class="{\'ctrlf-toggle-inactive\': !%2$s}">%4$s</span>'
            . '%5$s'
            . '</div>',
            $id,
            $this->esc($statePath),
            $this->esc($falseLabel),
            $this->esc($trueLabel),
            $messageHtml,
        );
    }
}
