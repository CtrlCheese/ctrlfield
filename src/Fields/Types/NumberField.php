<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasTextInputOptions;
use CtrlField\Fields\FieldDefinition;

final class NumberField extends FieldDefinition
{
    use HasTextInputOptions;

    private ?float $min = null;

    private ?float $max = null;

    private ?float $step = null;

    /** Smallest value accepted (checked on save). */
    public function min(int|float $min): static
    {
        $this->min = (float) $min;
        return $this;
    }

    /** Largest value accepted (checked on save). */
    public function max(int|float $max): static
    {
        $this->max = (float) $max;
        return $this;
    }

    /** Step of the arrows, e.g. 0.01 for prices. Default: 1 (whole numbers typed by hand are fine either way). */
    public function step(int|float $step): static
    {
        $this->step = $step > 0 ? (float) $step : null;
        return $this;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    public function getStep(): ?float
    {
        return $this->step;
    }

    public function getType(): FieldType
    {
        return FieldType::NUMBER;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), $this->textInputDefinition(), ['min' => $this->min, 'max' => $this->max, 'step' => $this->step]);
    }
}
