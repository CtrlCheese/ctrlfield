<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\Traits\BuildsFieldMap;
use FieldForge\Fields\Contracts\FlexibleContentInterface;

class SchemaValidationStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME = 'schema_validation';

    public function handle(PipelineContext $context): void
    {
        if (empty($context->fieldGroups)) {
            throw new PipelineException(
                errorCode:    'NO_SCHEMA',
                errorMessage: 'No field groups are registered for this post context.',
                fieldKey:     null,
                stageName:    self::NAME,
            );
        }

        $allowedKeys = array_flip(array_keys(self::buildFieldMap($context->fieldGroups)));

        // Strip unknown keys — silently discard anything not in the registered schema.
        $context->fields = array_filter(
            $context->fields,
            static fn(string $key): bool => array_key_exists($key, $allowedKeys),
            ARRAY_FILTER_USE_KEY
        );

        // For FlexibleContent fields, validate that every _layout value is a known layout key.
        $fieldMap = self::buildFieldMap($context->fieldGroups);

        foreach ($context->fields as $key => $value) {
            $definition = $fieldMap[$key] ?? null;

            if (! ($definition instanceof FlexibleContentInterface)) {
                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $validLayouts = array_flip($definition->getLayoutKeys());

            foreach ($value as $index => $instance) {
                if (! is_array($instance)) {
                    continue;
                }

                $layoutKey = $instance['_layout'] ?? '';

                if (! is_string($layoutKey) || ! array_key_exists($layoutKey, $validLayouts)) {
                    throw new PipelineException(
                        errorCode:    'VALIDATION_FAILED',
                        errorMessage: "Unknown layout '{$layoutKey}' in field '{$key}' at index {$index}.",
                        fieldKey:     $key,
                        stageName:    self::NAME,
                    );
                }
            }
        }
    }
}
