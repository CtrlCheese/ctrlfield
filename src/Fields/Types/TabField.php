<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

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
