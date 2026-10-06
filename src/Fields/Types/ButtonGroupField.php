<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class ButtonGroupField extends FieldDefinition
{
    /** @var array<string, string> */
    private array $options   = [];
    private bool  $allowNull = false;

    public function getType(): FieldType
    {
        return FieldType::BUTTON_GROUP;
    }

    /** @param array<string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function allowNull(bool $allow = true): static
    {
        $this->allowNull = $allow;
        return $this;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isAllowNull(): bool
    {
        return $this->allowNull;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'options'    => $this->options,
            'allow_null' => $this->allowNull,
        ]);
    }
}
