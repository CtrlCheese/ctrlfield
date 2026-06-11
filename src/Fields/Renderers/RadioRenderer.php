<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\RadioField;

final class RadioRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof RadioField) {
            foreach ($field->getOptions() as $value => $label) {
                $id     = $this->esc($this->inputId($field) . '-' . $value);
                $items .= sprintf(
                    '<label class="ff-radio-label"><input type="radio" id="%s" value="%s" x-model="%s" class="ff-radio"> %s</label>',
                    $id,
                    $this->esc((string) $value),
                    $this->esc($statePath),
                    $this->esc($label),
                );
            }
        }

        return sprintf('<div class="ff-radios">%s</div>', $items);
    }
}
