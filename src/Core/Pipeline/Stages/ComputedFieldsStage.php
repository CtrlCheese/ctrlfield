<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Fields\Types\ComputedField;

/**
 * Calculates computed field values after Sanitization, before Persistence.
 *
 * Each ComputedField receives the full sanitized field map and stores its
 * return value back into $context->fields. If the callback throws, a
 * PipelineException is raised with code COMPUTED_FIELD_ERROR.
 *
 * Also updates $context->indexedFields for computed fields with isIndex()==true,
 * because SanitizationStage runs before computed values exist.
 */
final class ComputedFieldsStage implements StageInterface
{
    public const NAME = 'computed_fields';

    public function handle(PipelineContext $context): void
    {
        foreach ($context->fieldGroups as $group) {
            foreach ($group->getFields() as $field) {
                if (! $field instanceof ComputedField) {
                    continue;
                }

                $key = $field->getKey();

                try {
                    $computed = $field->compute($context->fields);
                } catch (\Throwable $e) {
                    throw new \FieldForge\Core\Pipeline\PipelineException(
                        'COMPUTED_FIELD_ERROR',
                        sprintf(
                            'Computed field "%s" callback threw: %s',
                            $key,
                            $e->getMessage(),
                        ),
                        $key,
                        self::NAME,
                        $e,
                    );
                }

                $context->fields[$key] = $computed;

                // SanitizationStage built indexedFields before computed values existed.
                // Update it here so the index meta row is written by PersistenceStage.
                if ($field->isIndex()) {
                    $context->indexedFields[$key] = $computed;
                }
            }
        }
    }
}
