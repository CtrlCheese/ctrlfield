<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

interface RendererInterface
{
    /**
     * Renders the field HTML with Alpine.js bindings.
     *
     * @param \CtrlField\Fields\FieldDefinition $field     The field definition.
     * @param string                             $statePath Alpine expression for the value, e.g. adminState['key'].
     */
    public function render(\CtrlField\Fields\FieldDefinition $field, string $statePath): string;
}
