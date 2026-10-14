<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Concerns\HasTextInputOptions;
use CtrlField\Fields\FieldDefinition;

final class TextareaField extends FieldDefinition
{
    use HasTextInputOptions;

    private int $rows = 5;

    /** Visible lines of the textarea (default 5). */
    public function rows(int $rows): static
    {
        $this->rows = max(1, $rows);
        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getType(): FieldType
    {
        return FieldType::TEXTAREA;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), $this->textInputDefinition(), ['rows' => $this->rows]);
    }
}
