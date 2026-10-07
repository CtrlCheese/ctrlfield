<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\PostObjectField;

/** Search posts of the field's post types; one or several, unordered. */
final class PostObjectRenderer extends AbstractPickerRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $types    = $field instanceof PostObjectField ? $field->getPostTypes() : ['post'];
        $multiple = $field instanceof PostObjectField && $field->isMultiple();
        $max      = $field instanceof PostObjectField ? $field->getMaxItems() : null;
        $typesJs  = (string) wp_json_encode(array_values($types ?: ['post']));

        return $this->renderPicker($field, $statePath, 'post', "searchPosts(q, {$typesJs})", $multiple, $max, false);
    }
}
