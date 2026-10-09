<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\WysiwygField;

final class WysiwygRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $toolbar = $field instanceof WysiwygField ? $field->getToolbar() : 'full';
        $media   = $field instanceof WysiwygField ? $field->getMediaButtons() : true;

        // TinyMCE is attached in the browser by x-ctrlf-wysiwyg (assets/admin/src/wysiwyg.js),
        // one editor per instance, so it also works inside repeater rows and flexible sections.
        return sprintf(
            '<div class="ctrlf-wysiwyg-wrap"><textarea class="ctrlf-input ctrlf-input--wysiwyg wp-editor-area" rows="10" x-ctrlf-wysiwyg="%s" data-ctrlfield-field="%s" data-toolbar="%s" data-media="%s"></textarea></div>',
            $this->esc($statePath),
            $this->esc($field->getKey()),
            $this->esc($toolbar),
            $media ? '1' : '0',
        );
    }
}
