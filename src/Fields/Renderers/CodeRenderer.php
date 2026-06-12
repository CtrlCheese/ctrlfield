<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\CodeField;

final class CodeRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $language  = 'text';
        $rows      = 10;
        $wrapLines = false;

        if ($field instanceof CodeField) {
            $language  = $field->getLanguage();
            $rows      = $field->getRows();
            $wrapLines = $field->isWrapLines();
        }

        $fieldKey     = $this->esc($field->getKey());
        $escapedPath  = $this->esc($statePath);
        $escapedLang  = $this->esc($language);
        $escapedRows  = (string) $rows;
        $wrapLinesStr = $this->esc($wrapLines ? 'true' : 'false');

        return sprintf(
            '<div class="ff-code-editor-wrap"'
            . ' data-fieldforge-code="%1$s"'
            . ' data-language="%2$s"'
            . ' data-rows="%3$s"'
            . ' data-wrap-lines="%4$s">'
            . '<textarea id="ff-code-%1$s" class="ff-code-textarea" rows="%3$s"'
            . ' x-model="%5$s"></textarea>'
            . '</div>',
            $fieldKey,
            $escapedLang,
            $escapedRows,
            $wrapLinesStr,
            $escapedPath,
        );
    }
}
