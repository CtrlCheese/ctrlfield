<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\Contracts\RendererInterface;
use CtrlField\Fields\FieldDefinition;

abstract class AbstractRenderer implements RendererInterface
{
    /**
     * Wraps a field input with its label and visible_when x-show directive.
     * Called by MetaBoxRenderer — renderers themselves only output the input HTML.
     */
    abstract public function render(FieldDefinition $field, string $statePath): string;

    /**
     * Escapes a value for use inside an HTML attribute (double-quote delimited).
     * Deliberately uses ENT_COMPAT (not ENT_QUOTES) so single quotes in Alpine
     * JS expressions — e.g. adminState['key'] — are preserved and remain
     * evaluable by Alpine without entity decoding.
     */
    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function inputId(FieldDefinition $field): string
    {
        return 'ctrlf-' . $field->getKey();
    }

    protected function baseClasses(): string
    {
        return 'ctrlf-input';
    }

    /** Six-dot grip for drag handles (see x-ctrlf-handle in assets/admin/src/sortable.js). */
    public static function dragHandleIcon(): string
    {
        return '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
            . '<circle cx="7" cy="5" r="1.5"/><circle cx="13" cy="5" r="1.5"/>'
            . '<circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>'
            . '<circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/></svg>';
    }
}
