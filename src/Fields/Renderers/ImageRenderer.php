<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\ImageField;

/**
 * One image: the preview with an × to remove it, or the "Select Image" button —
 * never both, so a field only ever holds the one image the theme reads.
 */
final class ImageRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $id     = $this->esc($this->inputId($field));
        $sp     = $this->esc($statePath);
        $size   = $field instanceof ImageField ? $field->getPreviewSize() : 'medium';
        $sizeJs = $this->esc((string) json_encode($size));
        $class  = $this->esc('ctrlf-image-preview--' . $size);
        $remove = $this->esc(__('Remove image', 'ctrlfield'));
        $select = $this->esc(__('Select Image', 'ctrlfield'));

        return <<<HTML
<div class="ctrlf-image-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ctrlf-image-preview {$class}" x-show="{$sp} > 0" x-cloak>
        <img :src="getAttachmentUrl({$sp}, {$sizeJs})" class="ctrlf-image-thumb" alt="">
        <button type="button" class="ctrlf-image-remove" @click="{$sp} = 0" title="{$remove}" aria-label="{$remove}">
            <svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <button type="button" class="ctrlf-btn ctrlf-btn--upload" x-show="!({$sp} > 0)"
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'image')">
        {$select}
    </button>
</div>
HTML;
    }
}
