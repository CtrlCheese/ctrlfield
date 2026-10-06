<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\LinkField;

final class LinkRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $showTarget = $field instanceof LinkField ? $field->getShowTarget() : true;
        $readOnly   = $field->isReadOnly();
        $roAttr     = $readOnly ? ' disabled' : '';
        $id         = $this->esc($this->inputId($field));

        $urlInput = sprintf(
            '<input type="url" id="%s-url" x-model="%s.url" placeholder="https://" class="ctrlf-input ctrlf-input--url"%s>',
            $id,
            $this->esc($statePath),
            $roAttr,
        );

        $titleInput = sprintf(
            '<input type="text" id="%s-title" x-model="%s.title" placeholder="Link text" class="ctrlf-input ctrlf-input--text"%s>',
            $id,
            $this->esc($statePath),
            $roAttr,
        );

        $targetToggle = '';
        if ($showTarget) {
            $targetToggle = sprintf(
                '<label class="ctrlf-link-target"><input type="checkbox" %s x-bind:checked="%s.target === \'_blank\'" @change="%s.target = $event.target.checked ? \'_blank\' : \'_self\'"> Open in new tab</label>',
                $roAttr,
                $this->esc($statePath),
                $this->esc($statePath),
            );
        }

        return sprintf(
            '<div class="ctrlf-link-wrap"><div class="ctrlf-link-row ctrlf-link-row--url">%s</div><div class="ctrlf-link-row ctrlf-link-row--title">%s</div>%s</div>',
            $urlInput,
            $titleInput,
            $targetToggle,
        );
    }
}
