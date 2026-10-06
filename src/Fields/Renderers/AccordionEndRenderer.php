<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * Closes the accordion wrapper opened by AccordionRenderer.
 */
final class AccordionEndRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return '</div><!-- ctrlf-accordion-content --></div><!-- ctrlf-accordion -->';
    }
}
