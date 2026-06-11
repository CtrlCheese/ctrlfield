<?php

declare(strict_types=1);

namespace FieldForge\Fields\Contracts;

use FieldForge\Enums\FieldType;

interface FieldInterface
{
    public function getKey(): string;

    public function getType(): FieldType;

    /** @return array<string, mixed> */
    public function getDefinition(): array;
}
