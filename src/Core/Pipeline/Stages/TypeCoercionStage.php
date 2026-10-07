<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Traits\BuildsFieldMap;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\RangeField;

class TypeCoercionStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME = 'type_coercion';

    public function handle(PipelineContext $context): void
    {
        $fieldMap = self::buildFieldMap($context->fieldGroups);
        $coerced  = [];

        foreach ($context->fields as $key => $value) {
            $definition    = $fieldMap[$key] ?? null;
            $type          = $definition?->getType() ?? FieldType::TEXT;
            $coerced[$key] = self::coerce($value, $type, $key, $definition);
        }

        $context->fields = $coerced;
    }

    private static function coerce(mixed $value, FieldType $type, string $key, ?FieldDefinition $definition = null): mixed
    {
        return match ($type) {
            FieldType::NUMBER   => self::coerceNumber($value),
            FieldType::IMAGE,
            FieldType::FILE     => is_numeric($value) ? (int) $value : 0,
            FieldType::CHECKBOX => is_array($value) ? array_values($value) : [],
            FieldType::GROUP    => is_array($value) ? $value : [],
            FieldType::REPEATER => is_array($value) ? array_values($value) : [],
            FieldType::DATE     => self::coerceDate($value, $key),
            FieldType::TIME     => self::coerceTime($value, $key),
            FieldType::DATETIME => self::coerceDateTime($value, $key),
            FieldType::COLOR    => self::coerceColor($value),
            FieldType::LINK     => self::coerceLink($value),
            FieldType::RANGE            => self::coerceRange($value, $definition),
            FieldType::OEMBED           => is_string($value) ? trim($value) : (string) $value,
            FieldType::FLEXIBLE_CONTENT => $definition instanceof FlexibleContentInterface
                ? self::coerceFlexContent($value, $key, $definition)
                : (is_array($value) ? array_values($value) : []),
            // Pro relational fields — basic coercion; sanitization handles ID validation
            FieldType::POST_OBJECT,
            FieldType::PAGE_LINK     => is_array($value)
                ? array_values(array_map('intval', $value))
                : (is_numeric($value) ? (int) $value : 0),
            FieldType::TAXONOMY_TERM => is_array($value)
                ? array_values(array_map('intval', $value))
                : (is_numeric($value) ? (int) $value : 0),
            FieldType::RELATIONSHIP  => is_array($value)
                ? array_values(array_map('intval', $value))
                : [],
            // CLONE is expanded at register() time — never reaches the pipeline
            FieldType::CLONE         => $value,
            FieldType::GALLERY       => is_array($value)
                ? array_values(array_map('intval', $value))
                : [],
            FieldType::MAP           => self::coerceMap($value),
            default                  => is_string($value) ? $value : (string) $value,
        };
    }

    private static function coerceNumber(mixed $value): int|float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $str = (string) $value;

        return str_contains($str, '.') ? (float) $str : (int) $str;
    }

    private static function coerceDate(mixed $value, string $key): string
    {
        if (! is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $value);

        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            throw new PipelineException(
                errorCode:    'COERCION_FAILED',
                errorMessage: "Field '{$key}' expects ISO 8601 date (Y-m-d), got '{$value}'.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        return $value;
    }

    private static function coerceTime(mixed $value, string $key): string
    {
        if (! is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $value)) {
            throw new PipelineException(
                errorCode:    'COERCION_FAILED',
                errorMessage: "Field '{$key}' expects HH:MM time format, got '{$value}'.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        return $value;
    }

    private static function coerceDateTime(mixed $value, string $key): string
    {
        if (! is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Accept ISO 8601 with seconds (storage format) or without (browser datetime-local)
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s', $value)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i', $value);

        if ($dt === false) {
            throw new PipelineException(
                errorCode:    'COERCION_FAILED',
                errorMessage: "Field '{$key}' expects ISO 8601 datetime (Y-m-d\\TH:i:s), got '{$value}'.",
                fieldKey:     $key,
                stageName:    self::NAME,
            );
        }

        return $dt->format('Y-m-d\TH:i:s');
    }

    private static function coerceColor(mixed $value): string
    {
        $str = is_string($value) ? trim($value) : (string) $value;

        if ($str === '') {
            return '';
        }

        $hex = strtoupper($str);

        if (! str_starts_with($hex, '#')) {
            $hex = '#' . $hex;
        }

        return $hex;
    }

    /** @return array{url: string, title: string, target: string} */
    private static function coerceLink(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        return [
            'url'    => isset($value['url']) ? (string) $value['url'] : '',
            'title'  => isset($value['title']) ? (string) $value['title'] : '',
            'target' => (($value['target'] ?? '') === '_blank') ? '_blank' : '_self',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    /** @return array{lat: float, lng: float, zoom: int, address: string}|array{} */
    private static function coerceMap(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value) || ! is_numeric($value['lat'] ?? null) || ! is_numeric($value['lng'] ?? null)) {
            return []; // no coordinates = no location (not 0,0)
        }

        return [
            'lat'     => (float) $value['lat'],
            'lng'     => (float) $value['lng'],
            'zoom'    => isset($value['zoom']) && is_numeric($value['zoom']) ? (int) $value['zoom'] : 14,
            'address' => isset($value['address']) && is_string($value['address']) ? $value['address'] : '',
        ];
    }

    private static function coerceFlexContent(mixed $value, string $fieldKey, FlexibleContentInterface $definition): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach (array_values($value) as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            $layoutKey = is_string($instance['_layout'] ?? null) ? $instance['_layout'] : '';
            $layoutFields = $definition->getLayoutFields($layoutKey);

            $coercedInstance = ['_layout' => $layoutKey];

            if (isset($instance['_instance_id']) && is_string($instance['_instance_id'])) {
                $coercedInstance['_instance_id'] = $instance['_instance_id'];
            }

            foreach ($layoutFields as $subField) {
                $subKey = $subField->getKey();
                $subValue = $instance[$subKey] ?? null;
                $coercedInstance[$subKey] = self::coerce(
                    $subValue,
                    $subField->getType(),
                    "{$fieldKey}[{$layoutKey}][{$subKey}]",
                    $subField,
                );
            }

            $result[] = $coercedInstance;
        }

        return $result;
    }

    private static function coerceRange(mixed $value, ?FieldDefinition $definition): float
    {
        $float = is_numeric($value) ? (float) $value : 0.0;

        if ($definition instanceof RangeField) {
            $float = max($definition->getMin(), min($definition->getMax(), $float));
        }

        return $float;
    }
}
