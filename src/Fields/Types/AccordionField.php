<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class AccordionField extends FieldDefinition
{
    private bool $open = true;

    public function getType(): FieldType
    {
        return FieldType::ACCORDION;
    }

    public function isUiOnly(): bool
    {
        return true;
    }

    public function open(bool $open = true): static
    {
        $this->open = $open;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['open' => $this->open]);
    }
}
