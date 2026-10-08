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

        $rows = FieldLayout::render(array_values($field->getFields()), function (FieldDefinition $sub) use ($statePath): string {
            return self::fieldHtml($sub, "{$statePath}['{$sub->getKey()}']");
        });

        return sprintf('<div class="ctrlf-group">%s</div>', $rows);
    }
}
