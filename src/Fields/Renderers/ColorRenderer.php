<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\ColorField;

/**
 * A swatch (opens the browser's colour picker), the hex value to type or
 * paste, × to clear, a transparency slider with ->enableAlpha(), and the
 * palette (the theme's and the field's colours) underneath.
 */
final class ColorRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $palette = $field instanceof ColorField ? $field->getPalette() : [];
        $alpha   = $field instanceof ColorField && $field->isAlphaEnabled();
        $p       = $this->esc($statePath);
        $id      = $this->esc($this->inputId($field));
        $off     = $field->isReadOnly() ? ' disabled' : '';
        $clear   = $this->esc(__('Clear color', 'ctrlfield'));
        $pick    = $this->esc(__('Pick a color', 'ctrlfield'));
        $opacity = $this->esc(__('Opacity', 'ctrlfield'));

        $swatches = '';
        foreach ($palette as $hex) {
            $hexJs = $this->esc((string) wp_json_encode((string) $hex));
            $swatches .= '<button type="button" class="ctrlf-color__preset" style="--ctrlf-swatch:' . $this->esc((string) $hex) . '"'
                . ' :class="{ \'is-selected\': $ctrlfColor.sameColor(' . $p . ', ' . $hexJs . ') }"'
                . ' @click="' . $p . ' = $ctrlfColor.normalizeHex(' . $hexJs . ')" title="' . $this->esc((string) $hex) . '"'
                . ' aria-label="' . $this->esc((string) $hex) . '"' . $off . '></button>';
        }

        $alphaHtml = $alpha
            ? '<label class="ctrlf-color__alpha" x-show="' . $p . '"><span>' . $opacity . '</span>'
                . '<input type="range" min="0" max="100" :value="$ctrlfColor.opacityOf(' . $p . ')"' . $off
                . ' @input="' . $p . ' = $ctrlfColor.withOpacity(' . $p . ', $event.target.value)">'
                . '<output x-text="$ctrlfColor.opacityOf(' . $p . ') + \'%\'"></output></label>'
            : '';

        return '<div class="ctrlf-color">'
            . '<div class="ctrlf-color__row">'
            . '<label class="ctrlf-color__swatch" :class="{ \'is-empty\': !' . $p . ' }" :style="' . $p . ' ? { \'--ctrlf-swatch\': ' . $p . ' } : {}" title="' . $pick . '">'
            . '<input type="color" class="ctrlf-color__native" :value="$ctrlfColor.solidHex(' . $p . ')"' . $off
            . ' @input="' . $p . ' = ' . ($alpha ? '$ctrlfColor.withOpacity($event.target.value, $ctrlfColor.opacityOf(' . $p . '))' : '$event.target.value') . '"'
            . ' aria-label="' . $pick . '"></label>'
            . '<input type="text" id="' . $id . '" class="ctrlf-input ctrlf-color__hex" :value="' . $p . ' || \'\'" placeholder="#000000" maxlength="9" spellcheck="false"' . $off
            . ' @change="' . $p . ' = $ctrlfColor.normalizeHex($event.target.value); $event.target.value = ' . $p . '">'
            . '<button type="button" class="ctrlf-picker__x" x-show="' . $p . '" @click="' . $p . ' = \'\'" title="' . $clear . '" aria-label="' . $clear . '"' . $off . '>'
            . '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>'
            . '</div>'
            . $alphaHtml
            . ($swatches !== '' ? '<div class="ctrlf-color__palette">' . $swatches . '</div>' : '')
            . '</div>';
    }
}
