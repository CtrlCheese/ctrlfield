<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

final class WysiwygRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $key      = $field->getKey();
        $editorId = 'ctrlf_wysiwyg_' . $key;
        $sp       = $this->esc($statePath);

        ob_start();

        // wp_editor() outputs the TinyMCE instance. The Alpine bridge is
        // initialised from ctrlFieldAdmin.initWysiwyg() after the editor loads.
        if (function_exists('wp_editor')) {
            wp_editor('', $editorId, [
                'textarea_name' => '',   // managed by Alpine, not a form field
                'media_buttons' => true,
                'teeny'         => false,
                'quicktags'     => true,
                'tinymce'       => true,
            ]);
        } else {
            echo sprintf(
                '<textarea id="%s" class="ctrlf-input ctrlf-input--wysiwyg" rows="8"></textarea>',
                $this->esc($editorId)
            );
        }

        $html = (string) ob_get_clean();

        return sprintf(
            '<div class="ctrlf-wysiwyg-wrap" data-ctrlfield-wysiwyg="%s" data-statepath="%s">%s</div>',
            $this->esc($key),
            $sp,
            $html
        );
    }
}
