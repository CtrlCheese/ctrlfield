<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\CheckboxField;

final class CheckboxRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof CheckboxField) {
            foreach ($field->getOptions() as $value => $label) {
                $id     = $this->esc($this->inputId($field) . '-' . $value);
                $items .= sprintf(
                    '<label class="ff-checkbox-label"><input type="checkbox" id="%s" value="%s" x-model="%s" class="ff-checkbox"> %s</label>',
                    $id,
                    $this->esc((string) $value),
                    $this->esc($statePath),
                    $this->esc($label),
                );
            }
        }

        return sprintf('<div class="ff-checkboxes">%s</div>', $items);
    }
}
