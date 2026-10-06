<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\CheckboxField;

final class CheckboxRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof CheckboxField) {
            foreach ($field->getOptions() as $value => $label) {
                $id     = $this->esc($this->inputId($field) . '-' . $value);
                $items .= sprintf(
                    '<label class="ctrlf-checkbox-label"><input type="checkbox" id="%s" value="%s" x-model="%s" class="ctrlf-checkbox"> %s</label>',
                    $id,
                    $this->esc((string) $value),
                    $this->esc($statePath),
                    $this->esc($label),
                );
            }
        }

        return sprintf('<div class="ctrlf-checkboxes">%s</div>', $items);
    }
}
