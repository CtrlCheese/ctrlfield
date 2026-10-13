<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasTextInputOptions;
use CtrlField\Fields\FieldDefinition;

final class TextField extends FieldDefinition
{
    use HasTextInputOptions;

    public function getType(): FieldType
    {
        return FieldType::TEXT;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), $this->textInputDefinition(), []);
    }
}
