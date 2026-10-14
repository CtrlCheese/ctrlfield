<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TaxonomyField;

/** Terms of the field's taxonomy; one or several. */
final class TaxonomyRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $taxonomy = $field instanceof TaxonomyField ? $field->getTaxonomy() : '';

        return $this->renderPicker(
            $field,
            $statePath,
            'term:' . ($taxonomy !== '' ? $taxonomy : 'category'),
            $field instanceof TaxonomyField && $field->isMultiple(),
            $field instanceof TaxonomyField ? $field->getMinItems() : null,
            $field instanceof TaxonomyField ? $field->getMaxItems() : null,
            false,
        );
    }
}
