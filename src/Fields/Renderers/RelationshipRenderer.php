<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RelationshipField;

/** Related posts, in the order the editor drags them into. */
final class RelationshipRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $type     = $field instanceof RelationshipField ? $field->getRelatedPostType() : '';
        $multiple = ! ($field instanceof RelationshipField) || (bool) $field->getDefinition()['multiple'];

        return $this->renderPicker(
            $field,
            $statePath,
            'post:' . ($type !== '' ? $type : 'post'),
            $multiple,
            $field instanceof RelationshipField ? $field->getMinItems() : null,
            $field instanceof RelationshipField ? $field->getMaxItems() : null,
            true,
        );
    }
}
