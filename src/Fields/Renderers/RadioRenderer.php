<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RadioField;

/** Choices one per line or side by side, plain or as cards. */
final class RadioRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        if (! $field instanceof RadioField) {
            return '<div class="ctrlf-radios"></div>';
        }

        $off   = $field->isReadOnly() ? ' disabled' : '';
        $items = '';
        foreach ($field->getOptions() as $value => $label) {
            $items .= sprintf(
                '<label class="ctrlf-radio-label"><input type="radio" id="%s" value="%s" x-model="%s" class="ctrlf-radio"%s> <span>%s</span></label>',
                $this->esc($this->inputId($field) . '-' . $value),
                $this->esc((string) $value),
                $this->esc($statePath),
                $off,
                $this->esc((string) $label),
            );
        }

        return '<div class="' . $field->choiceClasses('ctrlf-radios') . '">' . $items . '</div>';
    }
}
