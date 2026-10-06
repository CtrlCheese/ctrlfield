<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\OembedField;

final class OembedRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $width    = $field instanceof OembedField ? $field->getPreviewWidth() : 640;
        $readOnly = $field->isReadOnly();
        $roAttr   = $readOnly ? ' disabled' : '';
        $id       = $this->esc($this->inputId($field));

        $input = sprintf(
            '<input type="url" id="%s" x-model="%s" placeholder="https://" class="ctrlf-input ctrlf-input--url"%s @blur="ctrlfieldOembedPreview(\'%s\', $el.value, %d)">',
            $id,
            $this->esc($statePath),
            $roAttr,
            $id,
            $width,
        );

        $preview = sprintf(
            '<div id="%s-preview" class="ctrlf-oembed-preview" x-show="%s !== \'\'"></div>',
            $id,
            $this->esc($statePath),
        );

        return sprintf('<div class="ctrlf-oembed-wrap">%s%s</div>', $input, $preview);
    }
}
