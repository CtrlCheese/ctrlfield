<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\Traits\BuildsFieldMap;
use FieldForge\Fields\Contracts\FieldSanitizerInterface;
use FieldForge\Enums\FieldType;
use FieldForge\Fields\Sanitizers\ColorSanitizer;
use FieldForge\Fields\Sanitizers\DateSanitizer;
use FieldForge\Fields\Sanitizers\DateTimeSanitizer;
use FieldForge\Fields\Sanitizers\LinkSanitizer;
use FieldForge\Fields\Sanitizers\OembedSanitizer;
use FieldForge\Fields\Sanitizers\RangeSanitizer;
use FieldForge\Fields\Sanitizers\TimeSanitizer;
use FieldForge\Storage\Contracts\StorageAdapterInterface;

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
                $sanitized[$key] = self::sanitize($value, $type);
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

    private static function sanitize(mixed $value, FieldType $type): mixed
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
            FieldType::GROUP,
            FieldType::REPEATER,
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
            // C-6: Code — preserve code characters, prevent XSS
            FieldType::CODE => function_exists('wp_kses_post') ? wp_kses_post((string) $value) : (string) $value,
            // C-1 / C-2: UI-only fields — never appear in payload, passthrough
            FieldType::TAB,
            FieldType::ACCORDION,
            FieldType::ACCORDION_END,
            FieldType::MESSAGE,
            FieldType::SEPARATOR => $value,
        };
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
