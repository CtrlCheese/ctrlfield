<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Contracts\RendererInterface;
use FieldForge\Fields\Renderers\ColorRenderer;
use FieldForge\Fields\Renderers\DateRenderer;
use FieldForge\Fields\Renderers\DateTimeRenderer;
use FieldForge\Fields\Renderers\LinkRenderer;
use FieldForge\Fields\Renderers\OembedRenderer;
use FieldForge\Fields\Renderers\RangeRenderer;
use FieldForge\Fields\Renderers\TimeRenderer;
use FieldForge\Fields\Renderers\TrueFalseRenderer;

/**
 * Maps FieldType enum values to their renderer instances.
 * Lazily initialised on first use; boot() can be called explicitly.
 */
final class RendererRegistry
{
    /** @var array<string, RendererInterface> */
    private static array $map = [];

    public static function boot(): void
    {
        self::$map = [
            FieldType::TEXT->value     => new TextRenderer(),
            FieldType::TEXTAREA->value => new TextareaRenderer(),
            FieldType::NUMBER->value   => new NumberRenderer(),
            FieldType::EMAIL->value    => new EmailRenderer(),
            FieldType::URL->value      => new UrlRenderer(),
            FieldType::SELECT->value   => new SelectRenderer(),
            FieldType::CHECKBOX->value => new CheckboxRenderer(),
            FieldType::RADIO->value    => new RadioRenderer(),
            FieldType::IMAGE->value    => new ImageRenderer(),
            FieldType::FILE->value     => new FileRenderer(),
            FieldType::WYSIWYG->value  => new WysiwygRenderer(),
            FieldType::GROUP->value    => new GroupRenderer(),
            FieldType::REPEATER->value => new RepeaterRenderer(),
            FieldType::DATE->value     => new DateRenderer(),
            FieldType::TIME->value     => new TimeRenderer(),
            FieldType::DATETIME->value => new DateTimeRenderer(),
            FieldType::COLOR->value    => new ColorRenderer(),
            FieldType::LINK->value     => new LinkRenderer(),
            FieldType::RANGE->value    => new RangeRenderer(),
            FieldType::OEMBED->value      => new OembedRenderer(),
            FieldType::TRUE_FALSE->value  => new TrueFalseRenderer(),
        ];
    }

    public static function resolve(FieldType $type): RendererInterface
    {
        if (empty(self::$map)) {
            self::boot();
        }

        return self::$map[$type->value] ?? new TextRenderer();
    }

    /** @param RendererInterface $renderer Allows Pro to override a renderer. */
    public static function register(FieldType $type, RendererInterface $renderer): void
    {
        self::$map[$type->value] = $renderer;
    }

    public static function reset(): void
    {
        self::$map = [];
    }
}
