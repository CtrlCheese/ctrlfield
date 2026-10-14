<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasTextInputOptions;
use CtrlField\Fields\FieldDefinition;

/** Masked text input. The value is stored as entered (like ACF) — not hashed. */
final class PasswordField extends FieldDefinition
{
    use HasTextInputOptions;

    public function getType(): FieldType
    {
        return FieldType::PASSWORD;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), $this->textInputDefinition(), []);
    }
}
