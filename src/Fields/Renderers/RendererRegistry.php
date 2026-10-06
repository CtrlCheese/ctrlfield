<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\RendererInterface;
use CtrlField\Fields\Renderers\AccordionEndRenderer;
use CtrlField\Fields\Renderers\AccordionRenderer;
use CtrlField\Fields\Renderers\ButtonGroupRenderer;
use CtrlField\Fields\Renderers\CodeRenderer;
use CtrlField\Fields\Renderers\ColorRenderer;
use CtrlField\Fields\Renderers\DateRenderer;
use CtrlField\Fields\Renderers\DateTimeRenderer;
use CtrlField\Fields\Renderers\IconRenderer;
use CtrlField\Fields\Renderers\LinkRenderer;
use CtrlField\Fields\Renderers\MessageRenderer;
use CtrlField\Fields\Renderers\OembedRenderer;
use CtrlField\Fields\Renderers\RangeRenderer;
use CtrlField\Fields\Renderers\SeparatorRenderer;
use CtrlField\Fields\Renderers\TabRenderer;
use CtrlField\Fields\Renderers\TimeRenderer;
use CtrlField\Fields\Renderers\TrueFalseRenderer;
use CtrlField\Fields\Renderers\UserRenderer;

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
        // Keep renderers registered before boot (Pro registers on plugins_loaded).
        self::$map = array_replace([
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
            FieldType::OEMBED->value        => new OembedRenderer(),
            FieldType::TRUE_FALSE->value    => new TrueFalseRenderer(),
            // C-1: Tab + Accordion
            FieldType::TAB->value           => new TabRenderer(),
            FieldType::ACCORDION->value     => new AccordionRenderer(),
            FieldType::ACCORDION_END->value => new AccordionEndRenderer(),
            // C-2: Message + Separator
            FieldType::MESSAGE->value       => new MessageRenderer(),
            FieldType::SEPARATOR->value     => new SeparatorRenderer(),
            // C-3: Button Group
            FieldType::BUTTON_GROUP->value  => new ButtonGroupRenderer(),
            // C-4: User
            FieldType::USER->value          => new UserRenderer(),
            // C-5: Icon
            FieldType::ICON->value          => new IconRenderer(),
            // C-6: Code
            FieldType::CODE->value          => new CodeRenderer(),
        ], self::$map);
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
        // Boot first: a non-empty map skips the lazy boot in resolve(), which left
        // every Core type on the TextRenderer fallback once Pro registered its own.
        if (empty(self::$map)) {
            self::boot();
        }

        self::$map[$type->value] = $renderer;
    }

    public static function reset(): void
    {
        self::$map = [];
    }
}
