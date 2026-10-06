<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\ColorField;

final class ColorRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $palette  = $field instanceof ColorField ? $field->getPalette() : [];
        $readOnly = $field->isReadOnly();
        $roAttr   = $readOnly ? ' disabled' : '';

        $picker = sprintf(
            '<input type="color" id="%s" x-model="%s" class="ctrlf-input ctrlf-input--color"%s>',
            $this->esc($this->inputId($field)),
            $this->esc($statePath),
            $roAttr,
        );

        if (empty($palette)) {
            return $picker;
        }

        $swatches = '';
        foreach ($palette as $hex) {
            $swatches .= sprintf(
                '<button type="button" class="ctrlf-color-swatch" style="background:%s" @click="%s = \'%s\'" title="%s"></button>',
                $this->esc($hex),
                $this->esc($statePath),
                $this->esc($hex),
                $this->esc($hex),
            );
        }

        return sprintf(
            '<div class="ctrlf-color-wrap">%s<div class="ctrlf-color-palette">%s</div></div>',
            $picker,
            $swatches,
        );
    }
}
