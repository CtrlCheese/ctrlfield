<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\ButtonGroupField;

final class ButtonGroupRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $items = '';

        if ($field instanceof ButtonGroupField) {
            foreach ($field->getOptions() as $value => $label) {
                // A JS string literal: a ' in a choice value cannot end the expression.
                $escapedValue = $this->esc((string) wp_json_encode((string) $value));
                $escapedLabel = $this->esc((string) $label);
                $escapedPath  = $this->esc($statePath);

                $items .= sprintf(
                    '<button type="button" role="radio"'
                    . ' :aria-checked="%1$s === %2$s ? \'true\' : \'false\'"'
                    . ' :class="{\'is-active\': %1$s === %2$s}"'
                    . ' @click="%1$s = %2$s"'
                    . ' class="ctrlf-btn-group-item">'
                    . '%3$s'
                    . '</button>',
                    $escapedPath,
                    $escapedValue,
                    $escapedLabel,
                );
            }
        }

        return sprintf('<div class="ctrlf-button-group" role="radiogroup">%s</div>', $items);
    }
}
