<?php

declare(strict_types=1);

namespace CtrlField\Admin\MetaBox;

/**
 * TinyMCE settings for WYSIWYG fields, built the way WordPress builds them for
 * its own editor, so a theme's editor setup applies to every field: toolbars
 * (mce_buttons*), formats (tiny_mce_before_init: style_formats, block_formats…),
 * plugins and editor CSS.
 *
 * A hidden template editor is printed once per screen; assets/admin/src/wysiwyg.js
 * copies its settings (tinyMCEPreInit) into each field. Same approach as ACF.
 */
final class WysiwygEditorSettings
{
    public const TEMPLATE_ID = 'ctrlf_mce_template';

    /** Default "Basic" toolbar (ACF's). */
    private const BASIC = ['bold', 'italic', 'underline', 'blockquote', 'strikethrough', 'bullist', 'numlist', 'alignleft', 'aligncenter', 'alignright', 'undo', 'redo', 'link', 'fullscreen'];

    /** WordPress' default editor rows, before the mce_buttons* filters. */
    private const ROW_1 = ['formatselect', 'bold', 'italic', 'bullist', 'numlist', 'blockquote', 'alignleft', 'aligncenter', 'alignright', 'link', 'wp_more', 'spellchecker', 'fullscreen', 'wp_adv'];
    private const ROW_2 = ['strikethrough', 'hr', 'forecolor', 'pastetext', 'removeformat', 'charmap', 'outdent', 'indent', 'undo', 'redo', 'wp_help'];

    /**
     * Named toolbars a field can pick (->toolbar('basic')): "full" and "basic",
     * plus whatever a theme adds. Themes built for ACF keep working: the
     * acf/fields/wysiwyg/toolbars filter receives and returns the same format.
     *
     * @return array<string, array<string, string>> name => [toolbar1 => 'bold,italic', …]
     */
    public static function toolbars(): array
    {
        $id   = self::TEMPLATE_ID;
        $full = [
            1 => (array) apply_filters('mce_buttons', self::ROW_1, $id),
            2 => (array) apply_filters('mce_buttons_2', self::ROW_2, $id),
            3 => (array) apply_filters('mce_buttons_3', [], $id),
            4 => (array) apply_filters('mce_buttons_4', [], $id),
        ];
        $toolbars = ['Full' => $full, 'Basic' => [1 => self::BASIC]];

        /** ACF's filter: [Name => [row number => list of buttons]]. */
        $toolbars = apply_filters('acf/fields/wysiwyg/toolbars', $toolbars);
        $toolbars = apply_filters('ctrlfield/wysiwyg/toolbars', $toolbars);

        $out = [];
        foreach (is_array($toolbars) ? $toolbars : [] as $name => $rows) {
            $key = sanitize_title((string) $name);
            if ($key === '' || ! is_array($rows)) {
                continue;
            }
            $toolbar = [];
            for ($row = 1; $row <= 4; $row++) { // rows are keyed 1–4, as in ACF
                $buttons                     = $rows[$row] ?? [];
                $toolbar['toolbar' . $row] = is_array($buttons) ? implode(',', array_filter($buttons, 'is_string')) : '';
            }
            $out[$key] = $toolbar;
        }

        return $out;
    }

    /** The hidden template editor; its TinyMCE never starts (wp_skip_init). */
    public static function printTemplate(): void
    {
        if (! function_exists('wp_editor')) {
            return;
        }
        echo '<div id="ctrlf-mce-template" hidden aria-hidden="true">';
        wp_editor('', self::TEMPLATE_ID, [
            'textarea_rows' => 4,
            'media_buttons' => true,
            'tinymce'       => ['wp_skip_init' => true],
        ]);
        echo '</div>';
    }
}
