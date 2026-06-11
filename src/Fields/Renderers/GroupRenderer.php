<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\GroupField;

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
<div class="ff-field">
    <label class="ff-label" for="ff-{$this->esc($sub->getKey())}">{$label}</label>
    {$input}
</div>
HTML;
        }

        return sprintf('<div class="ff-group">%s</div>', $rows);
    }
}
