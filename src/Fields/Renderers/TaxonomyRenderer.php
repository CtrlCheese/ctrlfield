<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\TaxonomyField;

/** Search terms of one taxonomy; one or several. */
final class TaxonomyRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $taxonomy = $field instanceof TaxonomyField ? $field->getTaxonomy() : 'category';
        $multiple = $field instanceof TaxonomyField && $field->isMultiple();
        $max      = $field instanceof TaxonomyField ? $field->getMaxItems() : null;
        $taxJs    = (string) wp_json_encode($taxonomy !== '' ? $taxonomy : 'category');

        return $this->renderPicker($field, $statePath, 'term', "searchTerms(q, {$taxJs})", $multiple, $max, false);
    }
}
