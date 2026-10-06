<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class IconRenderer extends AbstractRenderer
{
    /** Common Dashicons slugs (without the dashicons- prefix). */
    private const DASHICONS = [
        'admin-appearance',
        'admin-collapse',
        'admin-comments',
        'admin-customizer',
        'admin-generic',
        'admin-home',
        'admin-links',
        'admin-media',
        'admin-network',
        'admin-page',
        'admin-plugins',
        'admin-post',
        'admin-settings',
        'admin-site',
        'admin-tools',
        'admin-users',
        'arrow-down',
        'arrow-left',
        'arrow-right',
        'arrow-up',
        'awards',
        'bell',
        'book',
        'building',
        'calendar',
        'camera',
        'cart',
        'category',
        'chart-bar',
        'chart-line',
        'clock',
        'cloud',
        'controls-play',
        'editor-bold',
        'editor-italic',
        'email',
        'external',
        'facebook',
        'flag',
        'format-image',
        'format-video',
        'groups',
        'heart',
        'hidden',
        'id-alt',
        'images-alt',
        'info',
        'instagram',
        'list-view',
        'location',
        'lock',
        'marker',
        'megaphone',
        'menu',
        'migrate',
        'minus',
        'money',
        'networking',
        'no',
        'performance',
        'phone',
        'plus',
        'products',
        'rss',
        'search',
        'share',
        'shield',
        'smartphone',
        'smiley',
        'sort',
        'star-empty',
        'star-filled',
        'store',
        'tag',
        'tickets-alt',
        'twitter',
        'universal-access',
        'update',
        'upload',
        'users-alt',
        'video-alt3',
        'visibility',
        'warning',
        'wordpress',
        'yes',
    ];

    public function render(FieldDefinition $field, string $statePath): string
    {
        $escapedPath = $this->esc($statePath);

        $iconButtons = '';
        foreach (self::DASHICONS as $slug) {
            $escapedSlug  = $this->esc($slug);
            $fullClass    = 'dashicons dashicons-' . $escapedSlug;
            $fullValue    = 'dashicons-' . $escapedSlug;
            $iconButtons .= sprintf(
                '<button type="button" class="ctrlf-icon-item"'
                . ' x-show="!iconSearch || \'%1$s\'.includes(iconSearch.toLowerCase())"'
                . ' @click="%2$s = \'%3$s\'; open = false">'
                . '<span class="%4$s"></span>'
                . '<span>%1$s</span>'
                . '</button>',
                $escapedSlug,
                $escapedPath,
                $this->esc($fullValue),
                $this->esc($fullClass),
            );
        }

        return sprintf(
            '<div class="ctrlf-icon-picker" x-data="{ open: false }">'
            . '<button type="button" class="ctrlf-icon-preview-btn" @click="open = true">'
            . '<span :class="%1$s ? \'dashicons \' + %1$s : \'ctrlf-icon-empty\'" style="font-size:24px"></span>'
            . '<span class="ctrlf-icon-label" x-text="%1$s || \'Select icon\'"></span>'
            . '</button>'
            . '<div class="ctrlf-icon-modal" x-show="open" @keydown.escape.window="open = false">'
            . '<div class="ctrlf-icon-modal-inner">'
            . '<div class="ctrlf-icon-modal-header">'
            . '<input type="text" x-model="iconSearch" placeholder="Search icons..." class="ctrlf-input">'
            . '<button type="button" @click="open = false">&#10005;</button>'
            . '</div>'
            . '<div class="ctrlf-icon-grid">%2$s</div>'
            . '<button type="button" class="button" @click="%1$s = \'\'; open = false">Clear</button>'
            . '</div>'
            . '</div>'
            . '</div>',
            $escapedPath,
            $iconButtons,
        );
    }
}
