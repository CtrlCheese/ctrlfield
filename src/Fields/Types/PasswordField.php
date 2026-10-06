<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/** Masked text input. The value is stored as entered (like ACF) — not hashed. */
final class PasswordField extends FieldDefinition
{
    public function getType(): FieldType
    {
        return FieldType::PASSWORD;
    }
}
