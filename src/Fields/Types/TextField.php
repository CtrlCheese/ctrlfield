<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class TextField extends FieldDefinition
{
    public function getType(): FieldType
    {
        return FieldType::TEXT;
    }
}
