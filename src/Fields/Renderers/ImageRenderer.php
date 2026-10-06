<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class ImageRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $key = $this->esc($field->getKey());
        $id  = $this->esc($this->inputId($field));
        $sp  = $this->esc($statePath);

        return <<<HTML
<div class="ctrlf-image-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ctrlf-image-preview" x-show="{$sp} > 0" x-cloak>
        <img :src="getAttachmentUrl({$sp})" class="ctrlf-image-thumb" alt="">
        <button type="button" class="ctrlf-btn ctrlf-btn--remove" @click="{$sp} = 0">Remove</button>
    </div>
    <button type="button" class="ctrlf-btn ctrlf-btn--upload"
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'image')">
        Select Image
    </button>
</div>
HTML;
    }
}
