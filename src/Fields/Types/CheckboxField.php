<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasChoiceLayout;
use CtrlField\Fields\FieldDefinition;

final class CheckboxField extends FieldDefinition
{
    use HasChoiceLayout;

    private bool $toggleAll = false;

    /** A "Select all" choice above the options. */
    public function toggleAll(bool $toggleAll = true): static
    {
        $this->toggleAll = $toggleAll;
        return $this;
    }

    public function hasToggleAll(): bool
    {
        return $this->toggleAll;
    }

    /** @var array<string, string> */
    private array $options = [];

    public function getType(): FieldType
    {
        return FieldType::CHECKBOX;
    }

    /** @param array<string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['options' => $this->options], $this->choiceDefinition(), ['toggle_all' => $this->toggleAll]);
    }
}
