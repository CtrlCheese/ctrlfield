<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\PostObjectField;

/** Posts of the field's post types; one or several, unordered. */
final class PostObjectRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $types = ($field instanceof PostObjectField ? $field->getPostTypes() : []) ?: ['post'];

        return $this->renderPicker(
            $field,
            $statePath,
            'post:' . implode(',', $types),
            $field instanceof PostObjectField && $field->isMultiple(),
            $field instanceof PostObjectField ? $field->getMinItems() : null,
            $field instanceof PostObjectField ? $field->getMaxItems() : null,
            false,
            self::postTypeTabs(array_values($types)),
        );
    }
}
