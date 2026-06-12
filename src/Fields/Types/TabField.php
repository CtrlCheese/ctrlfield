<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class TabField extends FieldDefinition
{
    public function getType(): FieldType
    {
        return FieldType::TAB;
    }

    public function isUiOnly(): bool
    {
        return true;
    }
}
