<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * The content picker shared by Post Object, Relationship, Page Link, Taxonomy
 * and User (assets/admin/src/picker.js):
 *
 *  - a "Search or choose…" box right under the label: its dropdown lists the
 *    7 newest items (or the 7 best matches) and ends with "Browse all…", a
 *    dialog with search, tabs and the whole list;
 *  - the chosen items below it as lines — position number (the drag handle on
 *    hover, when the order matters), thumbnail, title, type · date, ×;
 *  - at the maximum the box hides until an item is removed; under the minimum
 *    a message asks for more (saving is refused too: RulesVerificationStage).
 *
 * Each picker has its own Alpine scope and writes straight to the field's
 * state path, so it also works inside repeater and flexible rows.
 */
abstract class AbstractPickerRenderer extends AbstractRenderer
{
    /**
     * @param string                                     $source "post:page,service" | "term:category" | "user:editor,author" (PickerController)
     * @param list<array{source: string, label: string}> $tabs   one per post type when there are several
     */
    protected function renderPicker(
        FieldDefinition $field,
        string $statePath,
        string $source,
        bool $multiple,
        ?int $min,
        ?int $max,
        bool $sortable,
        array $tabs = [],
    ): string {
        $p      = $this->esc($statePath);
        $config = $this->esc((string) wp_json_encode([
            'source'   => $source,
            'multiple' => $multiple,
            'min'      => $min,
            'max'      => $multiple ? $max : 1,
            'tabs'     => $tabs,
            'title'    => (string) ($field->getDefinition()['label'] ?? ''),
        ], JSON_HEX_APOS | JSON_HEX_QUOT));

        $drag     = $this->esc(__('Drag to reorder (or use the arrow keys)', 'ctrlfield'));
        $remove   = $this->esc(__('Remove', 'ctrlfield'));
        $choose   = $this->esc(__('Search or choose…', 'ctrlfield'));
        $browse   = $this->esc(__('Browse all…', 'ctrlfield'));
        $loading  = $this->esc(__('Loading…', 'ctrlfield'));
        $none     = $this->esc(__('Nothing found.', 'ctrlfield'));
        $disabled = $field->isReadOnly() ? ' disabled' : '';
        /* translators: %d: minimum number of items */
        $minText  = $min !== null ? $this->esc(sprintf(_n('Choose at least %d item.', 'Choose at least %d items.', $min, 'ctrlfield'), $min)) : '';
        /* translators: %d: maximum number of items */
        $maxText  = $multiple && $max !== null ? $this->esc(sprintf(_n('Maximum of %d item reached.', 'Maximum of %d items reached.', $max, 'ctrlfield'), $max)) : '';

        $sortAttr = $multiple && $sortable ? ' x-ctrlf-sort="' . $p . '"' : '';
        // The position number; on hover (or keyboard focus) the drag handle takes its place.
        $handle   = $multiple && $sortable
            ? '<button type="button" class="ctrlf-row__handle ctrlf-picker__handle" x-ctrlf-handle aria-label="' . $drag . '" title="' . $drag . '">'
                . '<span class="ctrlf-picker__num" x-text="idx + 1"></span>' . self::dragHandleIcon() . '</button>'
            : '';
        $item     = $multiple && $sortable ? ' data-ctrlf-item' : '';
        $id       = $this->esc($this->inputId($field));

        $thumb = static fn (string $expr): string =>
            '<span class="ctrlf-picker__thumb" :class="{ \'is-empty\': !' . $expr . '.thumbnail }">'
            . '<img :src="' . $expr . '.thumbnail || \'\'" alt="" x-show="' . $expr . '.thumbnail" loading="lazy"></span>';

        return '<div class="ctrlf-picker" x-data="ctrlfPicker(' . $config . ')"'
            . ' @click.outside="open = false" @keydown.escape="open = false">'

            // Search or choose: the 7 newest, then "Browse all…".
            . '<div class="ctrlf-picker__combo" x-show="!full(' . $p . ')">'
            . '<input type="search" id="' . $id . '" class="ctrlf-input ctrlf-picker__search" placeholder="' . $choose . '" autocomplete="off"'
            . ' role="combobox" :aria-expanded="open" x-model="q"' . $disabled
            . ' @focus="focus()" @click="focus()" @input.debounce.250ms="open = true; load()"'
            . ' @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"'
            . ' @keydown.enter.prevent="active >= 0 && active < items.length ? (' . $p . ' = add(' . $p . ', items[active])) : (active === items.length ? browse(' . $p . ', (v) => ' . $p . ' = v, $el) : null)">'
            . '<ul class="ctrlf-picker__dropdown" role="listbox" x-show="open" x-cloak>'
            . '<template x-for="(item, i) in items" :key="item.id">'
            . '<li><button type="button" class="ctrlf-picker__option" :class="{ \'is-active\': i === active }"'
            . ' :disabled="list(' . $p . ').includes(item.id)" @mouseenter="active = i"'
            . ' @click="' . $p . ' = add(' . $p . ', item)">'
            . $thumb('item')
            . '<span class="ctrlf-picker__text"><strong x-text="item.title"></strong>'
            . '<small x-text="[item.meta, item.date].filter(Boolean).join(\' · \')"></small></span>'
            . '</button></li>'
            . '</template>'
            . '<li class="ctrlf-picker__status" x-show="loading">' . $loading . '</li>'
            . '<li class="ctrlf-picker__status" x-show="!loading && !items.length">' . $none . '</li>'
            . '<li><button type="button" class="ctrlf-picker__browse" :class="{ \'is-active\': active === items.length }"'
            . ' @mouseenter="active = items.length" @click="browse(' . $p . ', (v) => ' . $p . ' = v, $el)">' . $browse . '</button></li>'
            . '</ul>'
            . '</div>'
            // The chosen items.
            . '<ul class="ctrlf-picker__chosen"' . $sortAttr . ' x-show="list(' . $p . ').length">'
            . '<template x-for="(id, idx) in list(' . $p . ')" :key="id">'
            . '<li class="ctrlf-picker__line" :class="{ \'is-missing\': info(id).missing }"' . $item . '>'
            . $handle
            . $thumb('info(id)')
            . '<span class="ctrlf-picker__text"><strong x-text="info(id).title"></strong>'
            . '<small x-text="[info(id).meta, info(id).date].filter(Boolean).join(\' · \')"></small></span>'
            . '<button type="button" class="ctrlf-picker__x" @click="' . $p . ' = remove(' . $p . ', id)"'
            . ' aria-label="' . $remove . '" title="' . $remove . '"' . $disabled . '>'
            . '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>'
            . '</button>'
            . '</li>'
            . '</template>'
            . '</ul>'

            . ($minText !== '' ? '<p class="ctrlf-picker__error" role="alert" x-show="belowMin(' . $p . ')">' . $minText . '</p>' : '')
            . ($maxText !== '' ? '<p class="ctrlf-picker__limit" x-show="full(' . $p . ')">' . $maxText . '</p>' : '')

            . '</div>';
    }

    /**
     * One tab per post type (when there are several), for the dialog.
     *
     * @param  list<string> $types
     * @return list<array{source: string, label: string}>
     */
    protected static function postTypeTabs(array $types): array
    {
        if (count($types) < 2) {
            return [];
        }
        $tabs = [];
        foreach ($types as $type) {
            $object = function_exists('get_post_type_object') ? get_post_type_object($type) : null;
            $tabs[] = ['source' => 'post:' . $type, 'label' => $object !== null ? (string) $object->labels->name : $type];
        }

        return $tabs;
    }
}
