<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\LinkField;

/**
 * A link set in a dialog (assets/admin/src/link.js), like the WordPress
 * "Insert/edit link" dialog: a compact line when set (text · where it goes ·
 * Edit · Remove), a "Select link" button when empty. With ->multiple(), one
 * line per link, reordered by dragging, and "Add link" until ->max().
 */
final class LinkRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $p        = $this->esc($statePath);
        $cfg      = self::config($field);
        $styles   = $this->esc((string) wp_json_encode($cfg['styles'], JSON_HEX_APOS | JSON_HEX_QUOT));
        $config   = $this->esc((string) wp_json_encode($cfg, JSON_HEX_APOS | JSON_HEX_QUOT));
        $disabled = $field->isReadOnly() ? ' disabled' : '';
        $select   = $this->esc(__('Select link', 'ctrlfield'));

        if ($field instanceof LinkField && $field->isMultiple()) {
            return $this->renderList($field, $statePath, $config, $styles, $disabled);
        }

        $open = "\$ctrlfLink({$p}, (v) => {$p} = v, {$config}, \$el)";
        $line = $this->line($p, $styles, $open, "{$p} = { url: '', title: '', target: '_self' }", $disabled);

        return <<<HTML
<div class="ctrlf-linkfield">
    <template x-if="{$p} && {$p}.url">
        {$line}
    </template>
    <template x-if="!({$p} && {$p}.url)">
        <button type="button" class="ctrlf-btn ctrlf-btn--add ctrlf-linkfield__select" @click="{$open}"{$disabled}>{$select}</button>
    </template>
</div>
HTML;
    }

    /** Several links: one line each, dragged to reorder, "Add link" until ->max(). */
    private function renderList(LinkField $field, string $statePath, string $config, string $styles, string $disabled): string
    {
        $p      = $this->esc($statePath);
        $suffix = substr(md5($statePath), 0, 8);
        $item   = 'link_' . $suffix;
        $idx    = 'li_' . $suffix;
        $list   = "(Array.isArray({$p}) ? {$p} : [])";
        $max    = $field->getMax();
        $edit   = "\$ctrlfLink({$item}, (v) => {$p} = {$list}.map((x, j) => j === {$idx} ? v : x), {$config}, \$el, {$idx})";
        $remove = "{$p} = {$list}.filter((x, j) => j !== {$idx})";
        $line   = $this->line($item, $styles, $edit, $remove, $disabled, true);
        $add    = $this->esc(__('Add link', 'ctrlfield'));
        /* translators: %d: maximum number of links */
        $limit  = $this->esc(sprintf(_n('Maximum of %d link reached', 'Maximum of %d links reached', $max, 'ctrlfield'), $max));
        $open   = "\$ctrlfLink(null, (v) => {$p} = [...{$list}, v], {$config}, \$el, {$list}.length)";

        return <<<HTML
<div class="ctrlf-linkfield ctrlf-linkfield--multiple">
    <div class="ctrlf-linklist" x-ctrlf-sort="{$p}" x-show="{$list}.length">
        <template x-for="({$item}, {$idx}) in {$list}" :key="\$ctrlfKey({$item})">
            {$line}
        </template>
    </div>
    <div class="ctrlf-linkfield__foot">
        <button type="button" class="ctrlf-btn ctrlf-btn--add ctrlf-linkfield__select" x-show="{$list}.length < {$max}" @click="{$open}"{$disabled}>+ {$add}</button>
        <span class="ctrlf-rows__limit" x-show="{$list}.length >= {$max}">{$limit}</span>
    </div>
</div>
HTML;
    }

    /**
     * One set link: text · kind · where it goes · style / new tab / icon, with
     * Edit and Remove (and a drag handle in a list).
     */
    private function line(string $v, string $styles, string $edit, string $remove, string $disabled, bool $sortable = false): string
    {
        $editTxt = $this->esc(__('Edit', 'ctrlfield'));
        $remTxt  = $this->esc(__('Remove', 'ctrlfield'));
        $missing = $this->esc(__('The linked content no longer exists or is not published.', 'ctrlfield'));
        $newTab  = $this->esc(__('new tab', 'ctrlfield'));
        $drag    = $this->esc(__('Drag to reorder (or use the arrow keys)', 'ctrlfield'));
        $handle  = $sortable
            ? '<button type="button" class="ctrlf-row__handle" x-ctrlf-handle aria-label="' . $drag . '" title="' . $drag . '">' . self::dragHandleIcon() . '</button>'
            : '';
        $item = $sortable ? ' data-ctrlf-item' : '';

        return <<<HTML
<div class="ctrlf-linkfield__set" :class="{ 'is-missing': \$ctrlfLinkState({$v}).missing }"{$item}>
            {$handle}
            <span class="ctrlf-linkfield__icon" x-show="{$v}.icon" x-html="\$ctrlfIcon({$v}.icon, 18)"></span>
            <div class="ctrlf-linkfield__text">
                <strong class="ctrlf-linkfield__title" x-text="{$v}.title || \$ctrlfLinkState({$v}).title || {$v}.url"></strong>
                <span class="ctrlf-linkfield__meta">
                    <span x-text="\$ctrlfLinkState({$v}).kind"></span>
                    <span class="ctrlf-linkfield__url" x-text="\$ctrlfLinkState({$v}).url || {$v}.url"></span>
                    <span class="ctrlf-linkfield__badge" x-show="{$v}.style" x-text="({$styles})[{$v}.style] ?? {$v}.style"></span>
                    <span class="ctrlf-linkfield__badge" x-show="{$v}.target === '_blank'">{$newTab}</span>
                </span>
                <span class="ctrlf-linkfield__warning" x-show="\$ctrlfLinkState({$v}).missing">{$missing}</span>
            </div>
            <button type="button" class="ctrlf-linkfield__action" @click="{$edit}"{$disabled}>{$editTxt}</button>
            <button type="button" class="ctrlf-linkfield__action ctrlf-linkfield__action--remove" @click="{$remove}"{$disabled}>{$remTxt}</button>
        </div>
HTML;
    }

    /**
     * What the dialog offers for this field: one tab per post type and per
     * taxonomy, anchors, the "new tab" toggle and button styles.
     *
     * @return array<string, mixed>
     */
    public static function config(FieldDefinition $field): array
    {
        $link     = $field instanceof LinkField ? $field : null;
        $types    = $link?->getPostTypes() ?? [];
        $tabs     = [];
        $allTypes = [];

        if ($types === []) {
            $types = array_values(array_diff(get_post_types(['public' => true]), ['attachment']));
        }
        foreach ($types as $type) {
            $object = get_post_type_object($type);
            if ($object === null || ! $object->public) {
                continue;
            }
            $allTypes[] = $type;
            $tabs[]     = ['source' => 'post:' . $type, 'label' => (string) $object->labels->name];
        }
        foreach ($link?->getTaxonomies() ?? [] as $taxonomy) {
            $tax = get_taxonomy($taxonomy);
            if ($tax !== false && $tax->public) {
                $tabs[] = ['source' => 'term:' . $taxonomy, 'label' => (string) $tax->labels->name];
            }
        }

        return [
            'all'        => $allTypes !== [] ? 'post:' . implode(',', $allTypes) : '',
            'tabs'       => $tabs,
            'anchors'    => $link?->getAnchors() ?? true,
            'showTarget' => $link?->getShowTarget() ?? true,
            'styles'     => (object) ($link?->getStyles() ?? []),
            // Style of a new link by its position (1st, 2nd…); the first style otherwise.
            'defaultStyles' => $link?->getDefaultStyles() ?? [],
            'icons'      => $link?->hasIcons() ?? false,
        ];
    }
}
