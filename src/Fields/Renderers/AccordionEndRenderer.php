<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;

/**
 * Closes the accordion wrapper opened by AccordionRenderer.
 */
final class AccordionEndRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return '</div><!-- ff-accordion-content --></div><!-- ff-accordion -->';
    }
}
