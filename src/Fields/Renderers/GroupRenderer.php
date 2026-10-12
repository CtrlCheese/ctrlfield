<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\GroupField;

/** A group's sub-fields as a block (default), label-left rows, or a one-row table. */
final class GroupRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        if (! ($field instanceof GroupField)) {
            return '';
        }

        $path = static fn (FieldDefinition $sub): string => "{$statePath}['{$sub->getKey()}']";

        if ($field->getLayout() === 'table') {
            $columns = SubFieldTable::columns($field->getFields());
            $cells   = self::withRowScope($statePath, static fn (): string => SubFieldTable::cells($columns, $path));

            return '<div class="ctrlf-group ctrlf-table" role="table" style="--ctrlf-cols:' . $this->esc(SubFieldTable::template($columns)) . '">'
                . SubFieldTable::head($columns)
                . '<div class="ctrlf-table__row" role="row">' . $cells . '</div>'
                . '</div>';
        }

        $rows = self::withRowScope($statePath, static fn (): string => FieldLayout::render(
            array_values($field->getFields()),
            static fn (FieldDefinition $sub): string => self::fieldHtml($sub, $path($sub)),
        ));

        $class = $field->getLayout() === 'row' ? 'ctrlf-group ctrlf-layout-row' : 'ctrlf-group';

        return sprintf('<div class="%s">%s</div>', $class, $rows);
    }
}
