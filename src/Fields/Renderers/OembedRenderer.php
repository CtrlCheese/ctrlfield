<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\OembedField;

/** The address, and the preview WordPress makes of it (oembed.js). */
final class OembedRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $width   = $field instanceof OembedField ? $field->getPreviewWidth() : 640;
        $p       = $this->esc($statePath);
        $id      = $this->esc($this->inputId($field));
        $off     = $field->isReadOnly() ? ' readonly' : '';
        $holder  = $this->esc($field->getPlaceholder() !== '' ? $field->getPlaceholder() : 'https://www.youtube.com/watch?v=…');
        $loading = $this->esc(__('Loading the preview…', 'ctrlfield'));
        $none    = $this->esc(__('No preview: WordPress does not embed this address. YouTube, Vimeo, Spotify, SoundCloud and others work.', 'ctrlfield'));

        return <<<HTML
<div class="ctrlf-oembed" x-data="ctrlfOembed({ width: {$width} })" x-init="load({$p})">
    <input type="url" id="{$id}" x-model="{$p}" placeholder="{$holder}" class="ctrlf-input ctrlf-input--url"{$off}
           @input.debounce.600ms="load({$p})">
    <p class="ctrlf-oembed__status" x-show="state === 'loading'"><span class="spinner is-active"></span>{$loading}</p>
    <p class="ctrlf-oembed__status ctrlf-oembed__status--none" x-show="state === 'none'">{$none}</p>
    <div class="ctrlf-oembed__preview" x-show="state === 'ready'" x-html="html"></div>
</div>
HTML;
    }
}
