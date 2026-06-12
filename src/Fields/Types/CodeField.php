<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class CodeField extends FieldDefinition
{
    private string $language  = 'text';
    private int    $rows      = 10;
    private bool   $wrapLines = false;

    public function getType(): FieldType
    {
        return FieldType::CODE;
    }

    public function language(string $lang): static
    {
        $this->language = $lang;
        return $this;
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;
        return $this;
    }

    public function wrapLines(bool $wrap = true): static
    {
        $this->wrapLines = $wrap;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function isWrapLines(): bool
    {
        return $this->wrapLines;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'language'   => $this->language,
            'rows'       => $this->rows,
            'wrap_lines' => $this->wrapLines,
        ]);
    }
}
