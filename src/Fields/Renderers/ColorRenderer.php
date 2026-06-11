<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\ColorField;

final class ColorRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $palette  = $field instanceof ColorField ? $field->getPalette() : [];
        $readOnly = $field->isReadOnly();
        $roAttr   = $readOnly ? ' disabled' : '';

        $picker = sprintf(
            '<input type="color" id="%s" x-model="%s" class="ff-input ff-input--color"%s>',
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
                '<button type="button" class="ff-color-swatch" style="background:%s" @click="%s = \'%s\'" title="%s"></button>',
                $this->esc($hex),
                $this->esc($statePath),
                $this->esc($hex),
                $this->esc($hex),
            );
        }

        return sprintf(
            '<div class="ff-color-wrap">%s<div class="ff-color-palette">%s</div></div>',
            $picker,
            $swatches,
        );
    }
}
