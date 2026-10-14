<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * One file: its icon, name, type and size with an × to remove it, or the
 * "Select File" button — never both, like the Image field.
 */
final class FileRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $id     = $this->esc($this->inputId($field));
        $sp     = $this->esc($statePath);
        $remove = $this->esc(__('Remove file', 'ctrlfield'));
        $select = $this->esc(__('Select File', 'ctrlfield'));
        $off    = $field->isReadOnly() ? ' disabled' : '';

        return <<<HTML
<div class="ctrlf-file-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <div class="ctrlf-file" x-show="{$sp} > 0" x-cloak :class="{ 'is-missing': fileInfo({$sp}).missing }">
        <span class="ctrlf-file__icon">
            <img :src="fileInfo({$sp}).icon || ''" alt="" x-show="fileInfo({$sp}).icon">
        </span>
        <span class="ctrlf-file__text">
            <a :href="fileInfo({$sp}).url || null" target="_blank" rel="noopener" x-text="fileInfo({$sp}).name"></a>
            <small x-text="[fileInfo({$sp}).ext, fileInfo({$sp}).size].filter(Boolean).join(' · ')"></small>
        </span>
        <button type="button" class="ctrlf-picker__x" @click="{$sp} = 0" title="{$remove}" aria-label="{$remove}"{$off}>
            <svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <button type="button" class="ctrlf-btn ctrlf-btn--upload" x-show="!({$sp} > 0)"{$off}
            @click="openMediaLibrary((id, url) => { {$sp} = id; }, 'file')">
        {$select}
    </button>
</div>
HTML;
    }
}
