<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class SelectField extends FieldDefinition
{
    /** @var array<string, string> */
    private array $options = [];

    /** null = automatic: a search box from more than 7 options. */
    private ?bool $searchable = null;

    /** More options than this get the searchable dropdown. */
    public const SEARCH_FROM = 8;

    public function getType(): FieldType
    {
        return FieldType::SELECT;
    }

    /** @param array<string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** Force the searchable dropdown on (or off); by default from 8 options. */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable ?? count($this->options) >= self::SEARCH_FROM;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['options' => $this->options]);
    }
}
