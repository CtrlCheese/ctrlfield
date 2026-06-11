<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\RepeaterField;

final class RepeaterRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        if (! ($field instanceof RepeaterField)) {
            return '';
        }

        $key         = $field->getKey();
        $escapedKey  = $this->esc($key);
        $escapedPath = $this->esc($statePath);

        // Build the row template — sub-fields use `row['sub_key']` as the Alpine model path
        $subFieldsHtml = '';
        $emptyRowJson  = [];

        foreach ($field->getFields() as $sub) {
            $subKey       = $sub->getKey();
            $subPath      = "row['{$subKey}']";
            $label        = $this->esc($sub->getDefinition()['label'] ?: $subKey);
            $subInput     = RendererRegistry::resolve($sub->getType())->render($sub, $subPath);
            $emptyRowJson[$subKey] = null;

            $subFieldsHtml .= <<<HTML
<div class="ff-field ff-field--inline">
    <label class="ff-label">{$label}</label>
    {$subInput}
</div>
HTML;
        }

        $emptyRow = htmlspecialchars(
            json_encode($emptyRowJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<div class="ff-repeater" x-data>
    <template x-for="(row, rowIdx) in {$escapedPath}" :key="rowIdx">
        <div class="ff-repeater-row">
            {$subFieldsHtml}
            <div class="ff-repeater-actions">
                <button type="button" class="ff-btn ff-btn--up"
                        @click="moveRowUp('{$escapedKey}', rowIdx)"
                        :disabled="rowIdx === 0"
                        title="Move up">&#8593;</button>
                <button type="button" class="ff-btn ff-btn--down"
                        @click="moveRowDown('{$escapedKey}', rowIdx)"
                        :disabled="rowIdx === {$escapedPath}.length - 1"
                        title="Move down">&#8595;</button>
                <button type="button" class="ff-btn ff-btn--remove"
                        @click="removeRow('{$escapedKey}', rowIdx)"
                        title="Remove row">&#10005;</button>
            </div>
        </div>
    </template>

    <button type="button" class="ff-btn ff-btn--add"
            @click="addRow('{$escapedKey}', {$emptyRow})">
        + Add Row
    </button>
</div>
HTML;
    }
}
