<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasTextInputOptions;
use CtrlField\Fields\FieldDefinition;

final class EmailField extends FieldDefinition
{
    use HasTextInputOptions;

    public function getType(): FieldType
    {
        return FieldType::EMAIL;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), $this->textInputDefinition(), []);
    }
}
