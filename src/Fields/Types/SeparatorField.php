<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class SeparatorField extends FieldDefinition
{
    public function getType(): FieldType
    {
        return FieldType::SEPARATOR;
    }

    public function isUiOnly(): bool
    {
        return true;
    }
}
