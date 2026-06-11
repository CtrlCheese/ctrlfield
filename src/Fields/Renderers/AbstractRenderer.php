<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\Contracts\RendererInterface;
use FieldForge\Fields\FieldDefinition;

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
        return 'ff-' . $field->getKey();
    }

    protected function baseClasses(): string
    {
        return 'ff-input';
    }
}
