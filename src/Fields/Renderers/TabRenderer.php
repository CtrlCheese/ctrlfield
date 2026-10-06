<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;

/**
 * Tab fields are rendered entirely by MetaBoxRenderer's tab-grouping logic.
 * This renderer intentionally returns an empty string.
 */
final class TabRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        return '';
    }
}
