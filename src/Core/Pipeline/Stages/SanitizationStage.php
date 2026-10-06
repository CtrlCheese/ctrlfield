<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\Contracts\StageInterface;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Traits\BuildsFieldMap;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Sanitizers\ColorSanitizer;
use CtrlField\Fields\Sanitizers\DateSanitizer;
use CtrlField\Fields\Sanitizers\DateTimeSanitizer;
use CtrlField\Fields\Sanitizers\LinkSanitizer;
use CtrlField\Fields\Sanitizers\OembedSanitizer;
use CtrlField\Fields\Sanitizers\RangeSanitizer;
use CtrlField\Fields\Sanitizers\TimeSanitizer;
use CtrlField\Storage\Contracts\StorageAdapterInterface;

class SanitizationStage implements StageInterface
{
    use BuildsFieldMap;

    public const NAME = 'sanitization';

    public function __construct(
        private readonly StorageAdapterInterface $adapter
    ) {}

    public function handle(PipelineContext $context): void
    {
        $fieldMap  = self::buildFieldMap($context->fieldGroups);
        $sanitized = [];
        $indexed   = [];

        foreach ($context->fields as $key => $value) {
            $definition = $fieldMap[$key] ?? null;
            $type       = $definition?->getType() ?? FieldType::TEXT;

            // Pro fields provide their own sanitizer via FieldSanitizerInterface.
            if ($definition instanceof FieldSanitizerInterface) {
                $sanitized[$key] = $definition->sanitizeForStorage($value);
            } else {
                $sanitized[$key] = self::sanitize($value, $type, $definition);
            }

            if ($definition?->isIndex() === true) {
                $indexed[$key] = $sanitized[$key];
            }
        }

        // Preserve hidden fields (visible_when): merge existing stored data first,
        // then overwrite with the current sanitized payload.
        // Fields absent from the current payload are untouched (never silently deleted).
        $existing = $this->adapter->load($context->postId) ?? [];

        $context->fields        = array_merge($existing, $sanitized);
        $context->indexedFields = $indexed;
    }

    /**
     * Entry point for external callers (e.g. CsvImporter) that need to sanitize
     * a single value outside of the full pipeline.
     */
    public static function sanitizeField(mixed $value, FieldDefinition $definition): mixed
    {
        if ($definition instanceof FieldSanitizerInterface) {
            return $definition->sanitizeForStorage($value);
        }

        return self::sanitize($value, $definition->getType(), $definition);
    }

    private static function sanitize(mixed $value, FieldType $type, ?FieldDefinition $definition = null): mixed
    {
        return match ($type) {
            FieldType::TEXT     => self::sanitizeText((string) $value),
            FieldType::TEXTAREA => self::sanitizeText((string) $value),
            FieldType::EMAIL    => self::sanitizeEmail((string) $value),
            FieldType::URL      => self::sanitizeUrl((string) $value),
            FieldType::WYSIWYG  => self::sanitizeHtml((string) $value),
            FieldType::NUMBER   => $value,
            FieldType::IMAGE,
            FieldType::FILE     => (int) $value,
            FieldType::SELECT,
            FieldType::RADIO    => self::sanitizeText((string) $value),
            FieldType::CHECKBOX => is_array($value)
                ? array_map(static fn(mixed $v) => self::sanitizeText((string) $v), $value)
                : [],
            FieldType::GROUP    => self::sanitizeGroup($value, $definition),
            FieldType::REPEATER => self::sanitizeRepeater($value, $definition),
            FieldType::FLEXIBLE_CONTENT,
            FieldType::POST_OBJECT,
            FieldType::TAXONOMY_TERM,
            FieldType::RELATIONSHIP,
            FieldType::GALLERY,
            FieldType::MAP,
            FieldType::CLONE    => is_array($value) ? $value : [],
            FieldType::COMPUTED   => $value, // passthrough — value set by ComputedFieldsStage
            FieldType::TRUE_FALSE => (int)(bool) $value, // stores 1 or 0
            FieldType::DATE     => (new DateSanitizer())->sanitize($value),
            FieldType::TIME     => (new TimeSanitizer())->sanitize($value),
            FieldType::DATETIME => (new DateTimeSanitizer())->sanitize($value),
            FieldType::COLOR    => (new ColorSanitizer())->sanitize($value),
            FieldType::LINK     => (new LinkSanitizer())->sanitize($value),
            FieldType::RANGE    => (new RangeSanitizer())->sanitize($value),
            FieldType::OEMBED   => (new OembedSanitizer())->sanitize($value),
            // C-3: Button Group — single string value
            FieldType::BUTTON_GROUP => self::sanitizeText((string) $value),
            // C-4: User — single int or array of ints
            FieldType::USER => is_array($value)
                ? array_map(static fn(mixed $v) => (int) $v, $value)
                : (int) $value,
            // C-5: Icon — string like "dashicons-admin-home"
            FieldType::ICON => self::sanitizeText((string) $value),
            // C-6: Code — strip all tags; stored as text, escaped on output
            FieldType::CODE => function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $value)
                : strip_tags((string) $value),
            // C-1 / C-2: UI-only fields — never appear in payload, passthrough
            FieldType::TAB,
            FieldType::ACCORDION,
            FieldType::ACCORDION_END,
            FieldType::MESSAGE,
            FieldType::SEPARATOR => $value,
        };
    }

    /**
     * Sanitizes a GROUP value by iterating each sub-field according to its schema type.
     * Unknown keys (not in schema) are stripped.
     *
     * @param  array<string, mixed> $subMap
     * @return array<string, mixed>
     */
    private static function sanitizeGroup(mixed $value, ?FieldDefinition $definition): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (! ($definition instanceof NestedFieldInterface)) {
            // No schema available — fall back to text-sanitizing all string leaves.
            return self::sanitizeFallback($value);
        }

        $subMap    = self::buildSubMap($definition->getFields());
        $sanitized = [];

        foreach ($value as $key => $subValue) {
            $subDef = $subMap[$key] ?? null;
            if ($subDef === null) {
                continue; // strip unknown keys
            }

            if ($subDef instanceof FieldSanitizerInterface) {
                $sanitized[$key] = $subDef->sanitizeForStorage($subValue);
            } else {
                $sanitized[$key] = self::sanitize($subValue, $subDef->getType(), $subDef);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitizes a REPEATER value by sanitizing each row as a group.
     *
     * @return list<array<string, mixed>>
     */
    private static function sanitizeRepeater(mixed $value, ?FieldDefinition $definition): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_map(
                static fn(mixed $row) => self::sanitizeGroup($row, $definition),
                $value
            )
        );
    }

    /**
     * Fallback for nested arrays without schema: text-sanitize every string leaf.
     *
     * @param  array<mixed, mixed> $arr
     * @return array<mixed, mixed>
     */
    private static function sanitizeFallback(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $out[$k] = is_array($v)
                ? self::sanitizeFallback($v)
                : self::sanitizeText((string) $v);
        }
        return $out;
    }

    /**
     * @param  array<int, \CtrlField\Fields\FieldDefinition> $fields
     * @return array<string, \CtrlField\Fields\FieldDefinition>
     */
    private static function buildSubMap(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $map[$field->getKey()] = $field;
        }
        return $map;
    }

    private static function sanitizeText(string $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value);
        }
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function sanitizeEmail(string $value): string
    {
        if (function_exists('sanitize_email')) {
            return sanitize_email($value);
        }
        $filtered = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        return is_string($filtered) ? $filtered : '';
    }

    private static function sanitizeUrl(string $value): string
    {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($value);
        }
        $filtered = filter_var(trim($value), FILTER_SANITIZE_URL);
        return is_string($filtered) ? $filtered : '';
    }

    private static function sanitizeHtml(string $value): string
    {
        if (function_exists('wp_kses_post')) {
            return wp_kses_post($value);
        }
        return strip_tags($value, ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4']);
    }
}
