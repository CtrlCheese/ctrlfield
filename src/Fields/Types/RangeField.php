<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class RangeField extends FieldDefinition
{
    private float $min       = 0.0;
    private float $max       = 100.0;
    private float $step      = 1.0;
    private bool  $showValue = false;

    public function getType(): FieldType
    {
        return FieldType::RANGE;
    }

    public function min(float $value): static
    {
        $this->min = $value;
        return $this;
    }

    public function max(float $value): static
    {
        $this->max = $value;
        return $this;
    }

    public function step(float $value): static
    {
        $this->step = $value;
        return $this;
    }

    /** Renders a live numeric display next to the slider. */
    public function showValue(bool $show = true): static
    {
        $this->showValue = $show;
        return $this;
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getStep(): float
    {
        return $this->step;
    }

    public function isShowValue(): bool
    {
        return $this->showValue;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'min'        => $this->min,
            'max'        => $this->max,
            'step'       => $this->step,
            'show_value' => $this->showValue,
        ]);
    }
}
