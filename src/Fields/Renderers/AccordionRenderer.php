<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\AccordionField;

/**
 * Opens the accordion wrapper. AccordionEndRenderer closes it.
 *
 * Uses Alpine x-data scoped to the field key to avoid conflicts between
 * multiple accordions on the same meta box.
 */
final class AccordionRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $isOpen = $field instanceof AccordionField ? $field->isOpen() : true;
        $label  = $this->esc($field->getDefinition()['label'] ?: $field->getKey());
        $openJs = $isOpen ? 'true' : 'false';

        return sprintf(
            '<div class="ff-accordion" x-data="{ open: %s }">'
            . '<button type="button" class="ff-accordion-toggle" @click="open = !open">'
            . '<span>%s</span>'
            . '<span class="ff-accordion-icon" :class="{\'is-open\': open}">&#9662;</span>'
            . '</button>'
            . '<div class="ff-accordion-content" x-show="open">',
            $openJs,
            $label,
        );
    }
}
