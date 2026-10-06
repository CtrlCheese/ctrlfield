<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Renderers;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Renderers\CheckboxRenderer;
use CtrlField\Fields\Renderers\EmailRenderer;
use CtrlField\Fields\Renderers\FileRenderer;
use CtrlField\Fields\Renderers\GroupRenderer;
use CtrlField\Fields\Renderers\ImageRenderer;
use CtrlField\Fields\Renderers\NumberRenderer;
use CtrlField\Fields\Renderers\RadioRenderer;
use CtrlField\Fields\Renderers\RendererRegistry;
use CtrlField\Fields\Renderers\RepeaterRenderer;
use CtrlField\Fields\Renderers\SelectRenderer;
use CtrlField\Fields\Renderers\TextareaRenderer;
use CtrlField\Fields\Renderers\TextRenderer;
use CtrlField\Fields\Renderers\UrlRenderer;
use CtrlField\Fields\Renderers\WysiwygRenderer;
use PHPUnit\Framework\TestCase;

class RendererRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        RendererRegistry::reset();
    }

    public function test_resolves_all_v1_field_types(): void
    {
        // PHP doesn't allow enum instances as array keys; use indexed pairs.
        $cases = [
            [FieldType::TEXT,     TextRenderer::class],
            [FieldType::TEXTAREA, TextareaRenderer::class],
            [FieldType::NUMBER,   NumberRenderer::class],
            [FieldType::EMAIL,    EmailRenderer::class],
            [FieldType::URL,      UrlRenderer::class],
            [FieldType::SELECT,   SelectRenderer::class],
            [FieldType::CHECKBOX, CheckboxRenderer::class],
            [FieldType::RADIO,    RadioRenderer::class],
            [FieldType::IMAGE,    ImageRenderer::class],
            [FieldType::FILE,     FileRenderer::class],
            [FieldType::WYSIWYG,  WysiwygRenderer::class],
            [FieldType::GROUP,    GroupRenderer::class],
            [FieldType::REPEATER, RepeaterRenderer::class],
        ];

        foreach ($cases as [$type, $expectedClass]) {
            $renderer = RendererRegistry::resolve($type);
            $this->assertInstanceOf($expectedClass, $renderer, "Wrong renderer for type {$type->value}");
        }
    }

    public function test_register_overrides_renderer(): void
    {
        $custom = new TextRenderer();
        RendererRegistry::register(FieldType::TEXTAREA, $custom);

        $this->assertSame($custom, RendererRegistry::resolve(FieldType::TEXTAREA));
    }

    public function test_registering_one_renderer_before_first_resolve_keeps_core_renderers(): void
    {
        // Pro registers its renderers on plugins_loaded, before any resolve().
        // Every Core type used to fall back to TextRenderer after that.
        $custom = new TextRenderer();
        RendererRegistry::register(FieldType::FLEXIBLE_CONTENT, $custom);

        $this->assertInstanceOf(SelectRenderer::class, RendererRegistry::resolve(FieldType::SELECT));
        $this->assertInstanceOf(CheckboxRenderer::class, RendererRegistry::resolve(FieldType::CHECKBOX));
        $this->assertSame($custom, RendererRegistry::resolve(FieldType::FLEXIBLE_CONTENT));
    }

    public function test_boot_keeps_renderers_registered_earlier(): void
    {
        $custom = new TextRenderer();
        RendererRegistry::register(FieldType::FLEXIBLE_CONTENT, $custom);
        RendererRegistry::boot();

        $this->assertSame($custom, RendererRegistry::resolve(FieldType::FLEXIBLE_CONTENT));
    }

    public function test_reset_clears_map(): void
    {
        RendererRegistry::boot();
        RendererRegistry::reset();

        // After reset, resolve() triggers lazy boot again — should still work
        $renderer = RendererRegistry::resolve(FieldType::TEXT);
        $this->assertInstanceOf(TextRenderer::class, $renderer);
    }
}
