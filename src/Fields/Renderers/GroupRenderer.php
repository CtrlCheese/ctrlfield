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

        $rows = self::withRowScope($statePath, static fn (): string => FieldLayout::render(
            array_values($field->getFields()),
            static fn (FieldDefinition $sub): string => self::fieldHtml($sub, "{$statePath}['{$sub->getKey()}']"),
        ));

        return sprintf('<div class="ctrlf-group">%s</div>', $rows);
    }
}
