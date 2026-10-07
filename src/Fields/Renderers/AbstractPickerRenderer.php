<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * Search-and-pick UI shared by Post Object, Relationship and Taxonomy.
 *
 * Each picker has its own Alpine scope ({ q, results }) and writes straight to
 * the field's state path, so it also works inside repeater rows. Search, labels
 * and pickValue() live in the ctrlFieldAdmin component (assets/admin/src).
 */
abstract class AbstractPickerRenderer extends AbstractRenderer
{
    /**
     * @param string $kind        'post' | 'term' — label namespace in pickerLabels.
     * @param string $searchCall  JS expression returning a promise of results, may use `q`.
     * @param bool   $sortable    Show up/down buttons (ordered lists).
     */
    protected function renderPicker(
        FieldDefinition $field,
        string $statePath,
        string $kind,
        string $searchCall,
        bool $multiple,
        ?int $max,
        bool $sortable,
    ): string {
        $p        = $statePath;
        $kindJs   = "'" . $kind . "'";
        $multiJs  = $multiple ? 'true' : 'false';
        $maxJs    = $max !== null ? (string) $max : 'null';
        $search   = 'results = await ' . $searchCall;

        $selected = $multiple
            ? '<ul class="ctrlf-picker__selected"' . ($sortable ? ' x-ctrlf-sort="' . $p . '"' : '') . '>'
                . '<template x-for="(id, idx) in (Array.isArray(' . $p . ') ? ' . $p . ' : [])" :key="id">'
                . '<li class="ctrlf-picker__item" data-ctrlf-item>'
                . ($sortable
                    ? '<button type="button" class="ctrlf-picker__handle" x-ctrlf-handle aria-label="' . $this->esc(__('Drag to reorder (or use the arrow keys)', 'ctrlfield')) . '">' . self::dragHandleIcon() . '</button>'
                    : '')
                . '<span x-text="itemLabel(' . $kindJs . ', id)"></span>'
                . '<button type="button" class="button-link ctrlf-picker__remove" @click="' . $p . '.splice(idx, 1)" aria-label="' . $this->esc(__('Remove', 'ctrlfield')) . '">&#215;</button>'
                . '</li>'
                . '</template>'
                . '</ul>'
            : '<div class="ctrlf-picker__selected" x-show="' . $p . '">'
                . '<span class="ctrlf-picker__item">'
                . '<span x-text="itemLabel(' . $kindJs . ', ' . $p . ')"></span>'
                . '<button type="button" class="button-link ctrlf-picker__remove" @click="' . $p . ' = null" aria-label="' . $this->esc(__('Remove', 'ctrlfield')) . '">&#215;</button>'
                . '</span>'
                . '</div>';

        $limit = $max !== null
            ? '<p class="description" x-show="Array.isArray(' . $p . ') && ' . $p . '.length >= ' . $max . '">'
                . $this->esc(sprintf(
                    /* translators: %d: maximum number of items */
                    __('Maximum of %d reached.', 'ctrlfield'),
                    $max
                ))
                . '</p>'
            : '';

        return '<div class="ctrlf-picker ctrlf-picker--' . $this->esc($kind) . '" x-data="{ q: \'\', results: [], open: false }"'
            . ' @click.outside="open = false">'
            . $selected
            . $limit
            . '<input type="search" id="' . $this->esc($this->inputId($field)) . '" class="ctrlf-input ctrlf-picker__search"'
            . ' placeholder="' . $this->esc(__('Search…', 'ctrlfield')) . '" autocomplete="off" x-model="q"'
            . ' @focus="open = true; if (!results.length) { ' . $this->esc($search) . ' }"'
            . ' @input.debounce.300ms="open = true; ' . $this->esc($search) . '">'
            . '<ul class="ctrlf-picker__results" x-show="open && results.length">'
            . '<template x-for="item in results" :key="item.id">'
            . '<li><button type="button" class="ctrlf-picker__result"'
            . ' @click="rememberLabel(' . $kindJs . ', item.id, item.title); '
            . $this->esc($p) . ' = pickValue(' . $this->esc($p) . ', item.id, ' . $multiJs . ', ' . $maxJs . '); '
            . ($multiple ? '' : 'open = false; q = \'\'; ')
            . '">'
            . '<span x-text="item.title"></span> <small x-text="item.meta"></small>'
            . '</button></li>'
            . '</template>'
            . '</ul>'
            . '</div>';
    }
}
