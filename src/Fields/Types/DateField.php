<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class DateField extends FieldDefinition
{
    private string  $format  = 'Y-m-d';
    private ?string $minDate = null;
    private ?string $maxDate = null;

    public function getType(): FieldType
    {
        return FieldType::DATE;
    }

    /** PHP date format used for display in the admin renderer only. Storage is always ISO 8601. */
    public function format(string $phpFormat): static
    {
        $this->format = $phpFormat;
        return $this;
    }

    /** ISO 8601 date string or 'today' / 'tomorrow'. */
    public function minDate(string $date): static
    {
        $this->minDate = $date;
        return $this;
    }

    /** ISO 8601 date string or 'today' / 'tomorrow'. */
    public function maxDate(string $date): static
    {
        $this->maxDate = $date;
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getMinDate(): ?string
    {
        return $this->minDate;
    }

    public function getMaxDate(): ?string
    {
        return $this->maxDate;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'format'   => $this->format,
            'min_date' => $this->minDate,
            'max_date' => $this->maxDate,
        ]);
    }
}
