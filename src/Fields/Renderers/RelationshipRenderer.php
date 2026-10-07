<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RelationshipField;

/** Ordered list of related posts (stored in the pivot table on save). */
final class RelationshipRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $type     = $field instanceof RelationshipField ? $field->getRelatedPostType() : '';
        $multiple = ! ($field instanceof RelationshipField) || $field->getDefinition()['multiple'];
        $max      = $field instanceof RelationshipField ? $field->getMaxItems() : null;
        $typeJs   = (string) wp_json_encode($type !== '' ? [$type] : ['post']);

        return $this->renderPicker($field, $statePath, 'post', "searchPosts(q, {$typeJs})", (bool) $multiple, $max, true);
    }
}
