<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\CheckboxField;

/** Choices one per line or side by side, plain or as cards, with an optional "Select all". */
final class CheckboxRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        if (! $field instanceof CheckboxField) {
            return '<div class="ctrlf-checkboxes"></div>';
        }

        $p     = $this->esc($statePath);
        $off   = $field->isReadOnly() ? ' disabled' : '';
        $items = '';
        foreach ($field->getOptions() as $value => $label) {
            $items .= sprintf(
                '<label class="ctrlf-checkbox-label"><input type="checkbox" id="%s" value="%s" x-model="%s" class="ctrlf-checkbox"%s> <span>%s</span></label>',
                $this->esc($this->inputId($field) . '-' . $value),
                $this->esc((string) $value),
                $p,
                $off,
                $this->esc((string) $label),
            );
        }

        $all = '';
        if ($field->hasToggleAll() && $field->getOptions() !== []) {
            $values = $this->esc((string) wp_json_encode(array_map('strval', array_keys($field->getOptions()))));
            $all    = '<label class="ctrlf-checkbox-label ctrlf-checkbox-label--all">'
                . '<input type="checkbox" class="ctrlf-checkbox"' . $off
                . ' :checked="Array.isArray(' . $p . ') && ' . $values . '.every((v) => ' . $p . '.includes(v))"'
                . ' :indeterminate="Array.isArray(' . $p . ') && ' . $p . '.length > 0 && !' . $values . '.every((v) => ' . $p . '.includes(v))"'
                . ' @change="' . $p . ' = $event.target.checked ? [...' . $values . '] : []">'
                . ' <span>' . $this->esc(__('Select all', 'ctrlfield')) . '</span></label>';
        }

        return $all . '<div class="' . $field->choiceClasses('ctrlf-checkboxes') . '">' . $items . '</div>';
    }
}
