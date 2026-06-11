<?php

declare(strict_types=1);

namespace FieldForge\Fields\Contracts;

use FieldForge\Fields\FieldDefinition;

/**
 * Implemented by field types that contain nested sub-fields
 * (GroupField, RepeaterField, FlexibleContentField).
 *
 * Used by pipeline stages and the nesting validator to introspect sub-field structure
 * without coupling to specific concrete types.
 */
interface NestedFieldInterface
{
    /**
     * Returns all direct child field definitions.
     *
     * @return array<int, FieldDefinition>
     */
    public function getFields(): array;
}
