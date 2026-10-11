<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Traits\BuildsFieldMap;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Conditions\ConditionEvaluator;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\ButtonGroupField;
use CtrlField\Fields\Types\CheckboxField;
use CtrlField\Fields\Types\LinkField;
use CtrlField\Fields\Types\RadioField;
use CtrlField\Fields\Types\RangeField;
use CtrlField\Fields\Types\SelectField;

class RulesVerificationStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME = 'rules_verification';

    public function handle(PipelineContext $context): void
    {
        $fieldMap = self::buildFieldMap($context->fieldGroups);

        $topScope = [$context->fields];

        foreach ($fieldMap as $key => $definition) {
            $value = $context->fields[$key] ?? null;

            // Hidden by its condition: the editor did not see it, nothing is required (C7.1).
            $condition = $definition->getConditionGroup();
            $hidden    = $condition !== null && ! ConditionEvaluator::isVisible($condition, $topScope);

            // FlexibleContent: min/max layout count and required sub-fields
            if ($definition instanceof FlexibleContentInterface) {
                self::verifyFlexContent($key, $value, $definition, $hidden ? null : $topScope);
                continue;
            }

            // Required sub-fields of repeater rows and groups, at any depth (C7.16).
            if (! $hidden) {
                self::verifyNested($key, $definition, $value, $topScope);
            }

            // Collection fields (PostObject, TaxonomyTerm, Relationship): min/max item count
            if ($definition instanceof CollectionConstraintsInterface) {
                self::verifyCollectionConstraints($key, $value, $definition);
                continue;
            }

            // Required check — LinkField has special logic: only url sub-key matters
            $isEffectivelyEmpty = $definition instanceof LinkField
                ? (! is_array($value) || empty($value['url']))
                : self::isEmpty($value);

            if ($definition->isRequired() && $isEffectivelyEmpty && ! $hidden) {
                throw new PipelineException(
                    errorCode:    'REQUIRED_FIELD',
                    errorMessage: "Field '{$key}' is required.",
                    fieldKey:     $key,
                    stageName:    self::NAME,
                );
            }

            // RangeField bounds check (belt-and-suspenders after TypeCoercionStage clamping)
            if ($definition instanceof RangeField && is_numeric($value)) {
                $float = (float) $value;
                if ($float < $definition->getMin() || $float > $definition->getMax()) {
                    throw new PipelineException(
                        errorCode:    'OUT_OF_RANGE',
                        errorMessage: "Field '{$key}' value {$float} is outside allowed range [{$definition->getMin()}, {$definition->getMax()}].",
                        fieldKey:     $key,
                        stageName:    self::NAME,
                    );
                }
            }

            // Option bounds: value must be one of the declared options
            if ($value !== null && $value !== '') {
                if ($definition instanceof SelectField || $definition instanceof RadioField || $definition instanceof ButtonGroupField) {
                    if (! array_key_exists((string) $value, $definition->getOptions())) {
                        throw new PipelineException(
                            errorCode:    'INVALID_OPTION',
                            errorMessage: "'{$value}' is not a valid option for field '{$key}'.",
                            fieldKey:     $key,
                            stageName:    self::NAME,
                        );
                    }
                }

                if ($definition instanceof CheckboxField) {
                    $selected = is_array($value) ? $value : [];
                    $options  = $definition->getOptions();

                    foreach ($selected as $selectedValue) {
                        if (! array_key_exists((string) $selectedValue, $options)) {
                            throw new PipelineException(
                                errorCode:    'INVALID_OPTION',
                                errorMessage: "'{$selectedValue}' is not a valid option for field '{$key}'.",
                                fieldKey:     $key,
                                stageName:    self::NAME,
                            );
                        }
                    }
                }
            }
        }
    }

    private static function verifyCollectionConstraints(string $key, mixed $value, CollectionConstraintsInterface $definition): void
    {
        $count = is_array($value)
            ? count($value)
            : (($value !== null && $value !== 0 && $value !== '') ? 1 : 0);

        $min = $definition->getMinItems();
        $max = $definition->getMaxItems();

        if ($min !== null && $count < $min) {
            throw new PipelineException(
                errorCode:    'VALIDATION_FAILED',
                errorMessage: "Field '{$key}' requires at least {$min} item(s), got {$count}.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        if ($max !== null && $count > $max) {
            throw new PipelineException(
                errorCode:    'VALIDATION_FAILED',
                errorMessage: "Field '{$key}' allows at most {$max} item(s), got {$count}.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }
    }

    /**
     * @param list<array<string, mixed>>|null $scopes Values around the field (top level);
     *                                               null = the field is hidden, skip required checks.
     */
    private static function verifyFlexContent(string $key, mixed $value, FlexibleContentInterface $definition, ?array $scopes = []): void
    {
        $instances = is_array($value) ? $value : [];
        $count     = count($instances);

        $min = $definition->getMinLayouts();
        $max = $definition->getMaxLayouts();

        if ($min !== null && $count < $min) {
            throw new PipelineException(
                errorCode:    'VALIDATION_FAILED',
                errorMessage: "Field '{$key}' requires at least {$min} layout(s), got {$count}.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        if ($max !== null && $count > $max) {
            throw new PipelineException(
                errorCode:    'VALIDATION_FAILED',
                errorMessage: "Field '{$key}' allows at most {$max} layout(s), got {$count}.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        // Per-layout limits (Pro FlexLayout::max()).
        if (method_exists($definition, 'getLayoutMax')) {
            $perLayout = array_count_values(array_map(
                static fn ($i): string => is_array($i) && is_string($i['_layout'] ?? null) ? $i['_layout'] : '',
                $instances
            ));
            foreach ($perLayout as $layoutKey => $n) {
                $layoutMax = $definition->getLayoutMax((string) $layoutKey);
                if ($layoutMax !== null && $n > $layoutMax) {
                    throw new PipelineException(
                        errorCode:    'VALIDATION_FAILED',
                        errorMessage: "Field '{$key}' allows at most {$layoutMax} '{$layoutKey}' section(s), got {$n}.",
                        fieldKey:     $key,
                        stageName:    self::NAME,
                    );
                }
            }
        }

        // Required sub-fields per section, with conditions evaluated in the section (C7.1)
        if ($scopes === null) {
            return;
        }
        foreach ($instances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            $layoutKey = is_string($instance['_layout'] ?? null) ? $instance['_layout'] : '';
            self::verifyRow($key, $definition->getLayoutFields($layoutKey), $instance, $scopes, "layout '{$layoutKey}'");
        }
    }

    /**
     * Rows of a repeater / the object of a group: required sub-fields, recursively.
     *
     * @param list<array<string, mixed>> $scopes innermost first
     */
    private static function verifyNested(string $topKey, FieldDefinition $definition, mixed $value, array $scopes, string $where = ''): void
    {
        if (! method_exists($definition, 'getFields') || ! is_array($value)) {
            return;
        }
        $type   = $definition->getType();
        $prefix = ($where !== '' ? $where . ' → ' : '') . "'{$definition->getKey()}'";

        if ($type === FieldType::REPEATER) {
            foreach (array_values($value) as $i => $row) {
                if (is_array($row)) {
                    self::verifyRow($topKey, $definition->getFields(), $row, $scopes, $prefix . ' row ' . ($i + 1));
                }
            }
        } elseif ($type === FieldType::GROUP) {
            self::verifyRow($topKey, $definition->getFields(), $value, $scopes, $prefix);
        }
    }

    /**
     * @param array<int|string, FieldDefinition> $fields
     * @param array<string, mixed>               $row
     * @param list<array<string, mixed>>         $outer  scopes around the row, innermost first
     */
    private static function verifyRow(string $topKey, array $fields, array $row, array $outer, string $where): void
    {
        $scopes = [$row, ...$outer];

        foreach ($fields as $sub) {
            if ($sub->isUiOnly()) {
                continue;
            }
            $condition = $sub->getConditionGroup();
            if ($condition !== null && ! ConditionEvaluator::isVisible($condition, $scopes)) {
                continue; // hidden in this row: keeps its value, not required
            }

            $value = $row[$sub->getKey()] ?? null;
            $empty = $sub instanceof LinkField
                ? (! is_array($value) || empty($value['url']))
                : self::isEmpty($value);

            if ($sub->isRequired() && $empty) {
                throw new PipelineException(
                    errorCode:    'REQUIRED_FIELD',
                    errorMessage: "Sub-field '{$sub->getKey()}' in {$where} of field '{$topKey}' is required.",
                    fieldKey:     $topKey,
                    stageName:    self::NAME,
                );
            }

            self::verifyNested($topKey, $sub, $value, $scopes, $where);
        }
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
