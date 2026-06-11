<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Contracts\NestedFieldInterface;
use FieldForge\Fields\Exceptions\InvalidNestingDepthException;
use FieldForge\Fields\FieldDefinition;

final class RepeaterField extends FieldDefinition implements NestedFieldInterface
{
    public const MAX_DEPTH = 3;

    /** @var array<int, FieldDefinition> */
    private array $fields = [];

    public function getType(): FieldType
    {
        return FieldType::REPEATER;
    }

    /**
     * @param array<int, FieldDefinition> $fields
     *
     * Traverses the full subtree at call time to enforce MAX_DEPTH.
     * The budget represents how many additional levels of RepeaterField
     * are still permitted below the current repeater.
     */
    public function fields(array $fields): static
    {
        self::assertDepthBudget($fields, self::MAX_DEPTH - 1);
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
            $field->validateFlexNesting($insideFlexible, true);
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

    /**
     * @param array<int, FieldDefinition> $fields
     *
     * Recursively walks the field tree. Each nested RepeaterField consumes
     * one unit of the remaining depth budget. When budget reaches 0 and
     * another RepeaterField is encountered, the definition is invalid.
     */
    private static function assertDepthBudget(array $fields, int $budget): void
    {
        foreach ($fields as $field) {
            if (! ($field instanceof self)) {
                continue;
            }

            if ($budget < 1) {
                throw new InvalidNestingDepthException(
                    sprintf(
                        'Repeater nesting exceeds the maximum allowed depth of %d.',
                        self::MAX_DEPTH
                    )
                );
            }

            self::assertDepthBudget($field->getFields(), $budget - 1);
        }
    }
}
