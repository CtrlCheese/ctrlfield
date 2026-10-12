<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Data\IconLibrary;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\IconField;

/**
 * The chosen icon with its name; clicking opens the icon dialog (icons.js),
 * the × clears it.
 */
final class IconRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $id     = $this->esc($this->inputId($field));
        $sp     = $this->esc($statePath);
        $libs   = $field instanceof IconField ? $field->getLibraries() : IconLibrary::LIBRARIES;
        $config = $this->esc((string) json_encode(['libraries' => $libs]));
        $choose = $this->esc((string) json_encode(__('Select icon', 'ctrlfield')));
        $change = $this->esc((string) json_encode(__('Change icon', 'ctrlfield')));
        $remove = $this->esc(__('Remove icon', 'ctrlfield'));

        return <<<HTML
<div class="ctrlf-icon-field">
    <input type="hidden" id="{$id}" x-model="{$sp}">
    <button type="button" class="ctrlf-icon-chip" :class="{ 'is-empty': !{$sp} }"
            :title="{$sp} ? {$change} : ''"
            @click="\$ctrlfIconPicker({$sp}, (v) => { {$sp} = v; }, {$config}, \$el)">
        <span class="ctrlf-icon-chip__glyph" x-html="\$ctrlfIcon({$sp}, 20)"></span>
        <span class="ctrlf-icon-chip__name" x-text="{$sp} ? {$sp}.replace(/^(lucide:|dashicons-)/, '') : {$choose}"></span>
    </button>
    <button type="button" class="ctrlf-icon-clear" x-show="{$sp}" x-cloak @click="{$sp} = ''" title="{$remove}" aria-label="{$remove}">
        <svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
    </button>
</div>
HTML;
    }
}
