<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\PageLinkField;

/** Pages (or posts of the field's types); templates get their URL. */
final class PageLinkRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $types = ($field instanceof PageLinkField ? $field->getPostTypes() : []) ?: ['page'];

        return $this->renderPicker(
            $field,
            $statePath,
            'post:' . implode(',', $types),
            $field instanceof PageLinkField && $field->isMultiple(),
            $field instanceof PageLinkField ? $field->getMinItems() : null,
            $field instanceof PageLinkField ? $field->getMaxItems() : null,
            true,
            self::postTypeTabs(array_values($types)),
        );
    }
}
