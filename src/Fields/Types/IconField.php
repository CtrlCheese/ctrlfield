<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class IconField extends FieldDefinition
{
    private string $library = 'dashicons';

    public function getType(): FieldType
    {
        return FieldType::ICON;
    }

    public function library(string $lib): static
    {
        $this->library = $lib;
        return $this;
    }

    public function getLibrary(): string
    {
        return $this->library;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['library' => $this->library]);
    }
}
