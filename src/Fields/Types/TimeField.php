<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class TimeField extends FieldDefinition
{
    private int $step = 1;

    public function getType(): FieldType
    {
        return FieldType::TIME;
    }

    /** Step in minutes. Passed as `step * 60` seconds to the HTML input. */
    public function step(int $minutes): static
    {
        $this->step = $minutes;
        return $this;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['step' => $this->step]);
    }
}
