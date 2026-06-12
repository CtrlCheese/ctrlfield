<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\TrueFalseField;

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
            ? sprintf(' <span class="ff-toggle-message">%s</span>', $this->esc($message))
            : '';

        return sprintf(
            '<div class="ff-toggle-wrap">'
            . '<span class="ff-toggle-label-off" :class="{\'ff-toggle-inactive\': %2$s}">%3$s</span>'
            . '<button type="button" id="%1$s" role="switch"'
            . ' :aria-checked="%2$s ? \'true\' : \'false\'"'
            . ' @click="%2$s = %2$s ? 0 : 1"'
            . ' :class="{\'is-on\': %2$s}"'
            . ' class="ff-toggle-button">'
            . '<span class="ff-toggle-thumb"></span>'
            . '</button>'
            . '<span class="ff-toggle-label-on" :class="{\'ff-toggle-inactive\': !%2$s}">%4$s</span>'
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
