<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\LinkField;

/**
 * A link set in a dialog (assets/admin/src/link.js), like the WordPress
 * "Insert/edit link" dialog: a compact line when set (text · where it goes ·
 * Edit · Remove), a "Select link" button when empty.
 */
final class LinkRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $p        = $this->esc($statePath);
        $config   = self::config($field);
        $styles   = $this->esc((string) wp_json_encode($config['styles'], JSON_HEX_APOS | JSON_HEX_QUOT));
        $config   = $this->esc((string) wp_json_encode($config, JSON_HEX_APOS | JSON_HEX_QUOT));
        $disabled = $field->isReadOnly() ? ' disabled' : '';

        $select  = $this->esc(__('Select link', 'ctrlfield'));
        $edit    = $this->esc(__('Edit', 'ctrlfield'));
        $remove  = $this->esc(__('Remove', 'ctrlfield'));
        $missing = $this->esc(__('The linked content no longer exists or is not published.', 'ctrlfield'));
        $newTab  = $this->esc(__('new tab', 'ctrlfield'));
        $open    = "\$ctrlfLink({$p}, (v) => {$p} = v, {$config}, \$el)";

        return <<<HTML
<div class="ctrlf-linkfield">
    <template x-if="{$p} && {$p}.url">
        <div class="ctrlf-linkfield__set" :class="{ 'is-missing': \$ctrlfLinkState({$p}).missing }">
            <div class="ctrlf-linkfield__text">
                <strong class="ctrlf-linkfield__title" x-text="{$p}.title || \$ctrlfLinkState({$p}).title || {$p}.url"></strong>
                <span class="ctrlf-linkfield__meta">
                    <span x-text="\$ctrlfLinkState({$p}).kind"></span>
                    <span class="ctrlf-linkfield__url" x-text="\$ctrlfLinkState({$p}).url || {$p}.url"></span>
                    <span class="ctrlf-linkfield__badge" x-show="{$p}.style" x-text="({$styles})[{$p}.style] ?? {$p}.style"></span>
                    <span class="ctrlf-linkfield__badge" x-show="{$p}.target === '_blank'">{$newTab}</span>
                </span>
                <span class="ctrlf-linkfield__warning" x-show="\$ctrlfLinkState({$p}).missing">{$missing}</span>
            </div>
            <button type="button" class="ctrlf-linkfield__action" @click="{$open}"{$disabled}>{$edit}</button>
            <button type="button" class="ctrlf-linkfield__action ctrlf-linkfield__action--remove" @click="{$p} = { url: '', title: '', target: '_self' }"{$disabled}>{$remove}</button>
        </div>
    </template>
    <template x-if="!({$p} && {$p}.url)">
        <button type="button" class="ctrlf-btn ctrlf-btn--add ctrlf-linkfield__select" @click="{$open}"{$disabled}>{$select}</button>
    </template>
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
        ];
    }
}
