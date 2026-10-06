<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

final class GroupField extends FieldDefinition implements NestedFieldInterface
{
    /** @var array<int, FieldDefinition> */
    private array $fields = [];

    public function getType(): FieldType
    {
        return FieldType::GROUP;
    }

    /** @param array<int, FieldDefinition> $fields */
    public function fields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }

    /** @return array<int, FieldDefinition> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function validateFlexNesting(bool $insideFlexible = false, bool $insideRepeater = false): void
    {
        foreach ($this->fields as $field) {
            $field->validateFlexNesting($insideFlexible, $insideRepeater);
        }
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'fields' => array_map(
                static fn(FieldDefinition $f) => $f->getDefinition(),
                $this->fields
            ),
        ]);
    }
}
