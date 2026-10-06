<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class FileRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $id = $this->esc($this->inputId($field));
        $sp = $this->esc($statePath);

        return <<<HTML
<div class="ctrlf-file-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ctrlf-file-name" x-show="{$sp} > 0" x-cloak>
        <span x-text="getAttachmentUrl({$sp})"></span>
        <button type="button" class="ctrlf-btn ctrlf-btn--remove" @click="{$sp} = 0">Remove</button>
    </div>
    <button type="button" class="ctrlf-btn ctrlf-btn--upload"
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'file')">
        Select File
    </button>
</div>
HTML;
    }
}
