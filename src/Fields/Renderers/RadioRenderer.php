<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RadioField;

final class RadioRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof RadioField) {
            foreach ($field->getOptions() as $value => $label) {
                $id     = $this->esc($this->inputId($field) . '-' . $value);
                $items .= sprintf(
                    '<label class="ctrlf-radio-label"><input type="radio" id="%s" value="%s" x-model="%s" class="ctrlf-radio"> %s</label>',
                    $id,
                    $this->esc((string) $value),
                    $this->esc($statePath),
                    $this->esc($label),
                );
            }
        }

        return sprintf('<div class="ctrlf-radios">%s</div>', $items);
    }
}
