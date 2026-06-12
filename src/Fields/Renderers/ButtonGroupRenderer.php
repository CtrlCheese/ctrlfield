<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\ButtonGroupField;

final class ButtonGroupRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof ButtonGroupField) {
            foreach ($field->getOptions() as $value => $label) {
                $escapedValue = $this->esc((string) $value);
                $escapedLabel = $this->esc((string) $label);
                $escapedPath  = $this->esc($statePath);

                $items .= sprintf(
                    '<button type="button" role="radio"'
                    . ' :aria-checked="%1$s === \'%2$s\' ? \'true\' : \'false\'"'
                    . ' :class="{\'is-active\': %1$s === \'%2$s\'}"'
                    . ' @click="%1$s = \'%2$s\'"'
                    . ' class="ff-btn-group-item">'
                    . '%3$s'
                    . '</button>',
                    $escapedPath,
                    $escapedValue,
                    $escapedLabel,
                );
            }
        }

        return sprintf('<div class="ff-button-group" role="radiogroup">%s</div>', $items);
    }
}
