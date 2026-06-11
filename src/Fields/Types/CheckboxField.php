<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class CheckboxField extends FieldDefinition
{
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
        return array_merge(parent::getDefinition(), ['options' => $this->options]);
    }
}
