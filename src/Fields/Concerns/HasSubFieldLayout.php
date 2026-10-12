<?php

declare(strict_types=1);

namespace CtrlField\Fields\Concerns;

/**
 * How a group or repeater lays out its sub-fields (as ACF does):
 *  - block: label above each field; ->width() puts fields side by side (default);
 *  - row:   label (and instructions) on the left, field on the right;
 *  - table: one column per sub-field, labels once as the header.
 */
trait HasSubFieldLayout
{
    private string $layout = 'block';

    /** @param 'block'|'row'|'table' $layout */
    public function layout(string $layout): static
    {
        $this->layout = in_array($layout, self::subFieldLayouts(), true) ? $layout : 'block';
        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    /** @return list<string> */
    public static function subFieldLayouts(): array
    {
        return ['block', 'row', 'table'];
    }
}
