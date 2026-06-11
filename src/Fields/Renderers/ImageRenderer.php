<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class ImageRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $key = $this->esc($field->getKey());
        $id  = $this->esc($this->inputId($field));
        $sp  = $this->esc($statePath);

        return <<<HTML
<div class="ff-image-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ff-image-preview" x-show="{$sp} > 0" x-cloak>
        <img :src="getAttachmentUrl({$sp})" class="ff-image-thumb" alt="">
        <button type="button" class="ff-btn ff-btn--remove" @click="{$sp} = 0">Remove</button>
    </div>
    <button type="button" class="ff-btn ff-btn--upload"
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'image')">
        Select Image
    </button>
</div>
HTML;
    }
}
