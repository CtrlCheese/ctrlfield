<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\SelectField;

/**
 * A short list: the native select. A long one (or ->searchable()): a button
 * showing the choice and a dropdown with a search box (select.js), in the
 * same look as the content pickers. "— None —" leads when not required.
 */
final class SelectRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $options  = $field instanceof SelectField ? $field->getOptions() : [];
        $none     = __('— None —', 'ctrlfield');
        $required = $field->isRequired();
        $p        = $this->esc($statePath);
        $id       = $this->esc($this->inputId($field));
        $off      = $field->isReadOnly() ? ' disabled' : '';

        if (! ($field instanceof SelectField) || ! $field->isSearchable()) {
            $html = '<option value="">' . ($required ? '' : $this->esc($none)) . '</option>';
            foreach ($options as $value => $label) {
                $html .= '<option value="' . $this->esc((string) $value) . '">' . $this->esc((string) $label) . '</option>';
            }
            return '<select id="' . $id . '" x-model="' . $p . '" class="ctrlf-input ctrlf-input--select"' . $off . '>' . $html . '</select>';
        }

        $list = [];
        foreach ($options as $value => $label) {
            $list[] = [(string) $value, (string) $label];
        }
        $config = $this->esc((string) wp_json_encode([
            'options'  => $list,
            'none'     => $required ? null : $none,
        ], JSON_HEX_APOS | JSON_HEX_QUOT));
        $search  = $this->esc(__('Search…', 'ctrlfield'));
        $choose  = $this->esc((string) wp_json_encode(__('Choose…', 'ctrlfield')));
        $nothing = $this->esc(__('Nothing found.', 'ctrlfield'));

        return <<<HTML
<div class="ctrlf-select" x-data="ctrlfSelect({$config})" @click.outside="open = false" @keydown.escape.stop="open = false">
    <button type="button" id="{$id}" class="ctrlf-input ctrlf-select__button" :class="{ 'is-empty': !labelOf({$p}) }"
            aria-haspopup="listbox" :aria-expanded="open"{$off}
            @click="toggle()" @keydown.down.prevent="toggle(true)">
        <span x-text="labelOf({$p}) || {$choose}"></span>
        <svg class="ctrlf-select__chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="M6 8l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
    </button>
    <div class="ctrlf-picker__dropdown ctrlf-select__dropdown" x-show="open" x-cloak>
        <input type="search" class="ctrlf-input ctrlf-select__search" placeholder="{$search}" autocomplete="off" x-ref="search" x-model="q"
               @input="active = 0" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
               @keydown.enter.prevent="matches()[active] && ({$p} = pick(matches()[active]))">
        <ul role="listbox">
            <template x-for="(o, i) in matches()" :key="o[0]">
                <li><button type="button" class="ctrlf-picker__option" role="option"
                            :class="{ 'is-active': i === active, 'is-selected': o[0] === String({$p} ?? '') }"
                            :aria-selected="o[0] === String({$p} ?? '')"
                            @mouseenter="active = i" @click="{$p} = pick(o)">
                    <span class="ctrlf-picker__text"><strong x-text="o[1]"></strong></span>
                </button></li>
            </template>
            <li class="ctrlf-picker__status" x-show="!matches().length">{$nothing}</li>
        </ul>
    </div>
</div>
HTML;
    }
}
