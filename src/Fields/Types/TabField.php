<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Starts a tab that runs until the next tab. The first tab of a set decides
 * where the tab bar sits: 'top' (default) or 'left' (a vertical list).
 */
final class TabField extends FieldDefinition
{
    private string $placement = 'top';

    public function getType(): FieldType
    {
        return FieldType::TAB;
    }

    public function isUiOnly(): bool
    {
        return true;
    }

    /** @param 'top'|'left' $placement */
    public function placement(string $placement): static
    {
        $this->placement = $placement === 'left' ? 'left' : 'top';
        return $this;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['placement' => $this->placement]);
    }
}
