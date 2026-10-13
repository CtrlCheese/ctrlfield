<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\UserField;

/** Users (of the field's roles); newest registered first while browsing. */
final class UserRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $roles = $field instanceof UserField ? $field->getRoles() : [];

        return $this->renderPicker(
            $field,
            $statePath,
            'user:' . implode(',', $roles),
            $field instanceof UserField && $field->isMultiple(),
            $field instanceof UserField ? $field->getMinItems() : null,
            $field instanceof UserField ? $field->getMaxItems() : null,
            false,
        );
    }
}
