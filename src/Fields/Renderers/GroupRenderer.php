<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\GroupField;

final class GroupRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        if (! ($field instanceof GroupField)) {
            return '';
        }

        $rows = '';

        foreach ($field->getFields() as $sub) {
            $subPath = "{$statePath}['{$sub->getKey()}']";
            $label   = $this->esc($sub->getDefinition()['label'] ?: $sub->getKey());
            $input   = RendererRegistry::resolve($sub->getType())->render($sub, $subPath);

            $rows .= <<<HTML
<div class="ctrlf-field">
    <label class="ctrlf-label" for="ctrlf-{$this->esc($sub->getKey())}">{$label}</label>
    {$input}
</div>
HTML;
        }

        return sprintf('<div class="ctrlf-group">%s</div>', $rows);
    }
}
