<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

final class FileRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $id = $this->esc($this->inputId($field));
        $sp = $this->esc($statePath);

        return <<<HTML
<div class="ff-file-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ff-file-name" x-show="{$sp} > 0" x-cloak>
        <span x-text="getAttachmentUrl({$sp})"></span>
        <button type="button" class="ff-btn ff-btn--remove" @click="{$sp} = 0">Remove</button>
    </div>
    <button type="button" class="ff-btn ff-btn--upload"
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'file')">
        Select File
    </button>
</div>
HTML;
    }
}
