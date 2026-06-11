<?php

declare(strict_types=1);

namespace FieldForge\Fields\Conditions;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Exceptions\InvalidOperatorForTypeException;
use FieldForge\Fields\FieldDefinition;

/**
 * Validates condition operator applicability against source field types.
 * Called at FieldGroup::register() time, when the full field map is known.
 */
final class ConditionValidator
{
    /** @var FieldType[] */
    private const NUMERIC_TYPES = [
        FieldType::NUMBER,
        FieldType::RANGE,
        FieldType::DATE,
        FieldType::TIME,
        FieldType::DATETIME,
    ];

    /** @var FieldType[] */
    private const TEXT_TYPES = [
        FieldType::TEXT,
        FieldType::TEXTAREA,
        FieldType::WYSIWYG,
    ];

    /** @var FieldType[] */
    private const OPTION_TYPES = [
        FieldType::SELECT,
        FieldType::CHECKBOX,
        FieldType::RADIO,
    ];

    /**
     * Validates all conditions on a field against the group's field map.
     * Skips conditions referencing fields not found in the map (e.g. cross-group, which is out of scope).
     *
     * @param array<string, FieldDefinition> $fieldMap
     * @throws InvalidOperatorForTypeException
     */
    public static function validate(FieldDefinition $field, array $fieldMap): void
    {
        $cg = $field->getConditionGroup();

        if ($cg === null) {
            return;
        }

        foreach ($cg->getConditions() as $condition) {
            [$sourceKey, $operatorStr, $value] = $condition;

            $sourceField = $fieldMap[$sourceKey] ?? null;

            if ($sourceField === null) {
                // Cross-group references are out of scope in v2 — skip silently.
                continue;
            }

            $operator   = ConditionOperator::tryFrom($operatorStr);
            $sourceType = $sourceField->getType();

            if ($operator === null) {
                throw new InvalidOperatorForTypeException(
                    "Unknown operator '{$operatorStr}' on condition for field '{$field->getKey()}'."
                );
            }

            self::assertApplicable($operator, $sourceType, $field->getKey(), $sourceKey);

            if (
                in_array($operator, [ConditionOperator::In, ConditionOperator::NotIn], true)
                && ! is_array($value)
            ) {
                throw new InvalidOperatorForTypeException(
                    sprintf(
                        "Operator '%s' on condition for field '%s' (source '%s') requires an array value.",
                        $operatorStr, $field->getKey(), $sourceKey,
                    )
                );
            }
        }
    }

    private static function assertApplicable(
        ConditionOperator $operator,
        FieldType $sourceType,
        string $fieldKey,
        string $sourceKey,
    ): void {
        $numericOnly = in_array($operator, [
            ConditionOperator::LessThan,
            ConditionOperator::LessThanOrEqual,
            ConditionOperator::GreaterThan,
            ConditionOperator::GreaterThanOrEqual,
        ], true);

        $textOnly = in_array($operator, [
            ConditionOperator::Contains,
            ConditionOperator::NotContains,
        ], true);

        $optionOnly = in_array($operator, [
            ConditionOperator::In,
            ConditionOperator::NotIn,
        ], true);

        if ($numericOnly && ! in_array($sourceType, self::NUMERIC_TYPES, true)) {
            throw new InvalidOperatorForTypeException(
                sprintf(
                    "Operator '%s' for field '%s' requires source '%s' to be one of [%s], got '%s'.",
                    $operator->value, $fieldKey, $sourceKey,
                    implode(', ', array_map(fn(FieldType $t) => $t->value, self::NUMERIC_TYPES)),
                    $sourceType->value,
                )
            );
        }

        if ($textOnly && ! in_array($sourceType, self::TEXT_TYPES, true)) {
            throw new InvalidOperatorForTypeException(
                sprintf(
                    "Operator '%s' for field '%s' requires source '%s' to be one of [%s], got '%s'.",
                    $operator->value, $fieldKey, $sourceKey,
                    implode(', ', array_map(fn(FieldType $t) => $t->value, self::TEXT_TYPES)),
                    $sourceType->value,
                )
            );
        }

        if ($optionOnly && ! in_array($sourceType, self::OPTION_TYPES, true)) {
            throw new InvalidOperatorForTypeException(
                sprintf(
                    "Operator '%s' for field '%s' requires source '%s' to be one of [%s], got '%s'.",
                    $operator->value, $fieldKey, $sourceKey,
                    implode(', ', array_map(fn(FieldType $t) => $t->value, self::OPTION_TYPES)),
                    $sourceType->value,
                )
            );
        }
    }
}
