<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\SelectField;

final class SelectRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $options = '<option value=""></option>';

        if ($field instanceof SelectField) {
            foreach ($field->getOptions() as $value => $label) {
                $options .= sprintf(
                    '<option value="%s">%s</option>',
                    $this->esc((string) $value),
                    $this->esc($label),
                );
            }
        }

        return sprintf(
            '<select id="%s" x-model="%s" class="ff-input ff-input--select">%s</select>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $options,
        );
    }
}
