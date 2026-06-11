<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Renderers;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Renderers\CheckboxRenderer;
use FieldForge\Fields\Renderers\EmailRenderer;
use FieldForge\Fields\Renderers\FileRenderer;
use FieldForge\Fields\Renderers\GroupRenderer;
use FieldForge\Fields\Renderers\ImageRenderer;
use FieldForge\Fields\Renderers\NumberRenderer;
use FieldForge\Fields\Renderers\RadioRenderer;
use FieldForge\Fields\Renderers\RendererRegistry;
use FieldForge\Fields\Renderers\RepeaterRenderer;
use FieldForge\Fields\Renderers\SelectRenderer;
use FieldForge\Fields\Renderers\TextareaRenderer;
use FieldForge\Fields\Renderers\TextRenderer;
use FieldForge\Fields\Renderers\UrlRenderer;
use FieldForge\Fields\Renderers\WysiwygRenderer;
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

    public function test_reset_clears_map(): void
    {
        RendererRegistry::boot();
        RendererRegistry::reset();

        // After reset, resolve() triggers lazy boot again — should still work
        $renderer = RendererRegistry::resolve(FieldType::TEXT);
        $this->assertInstanceOf(TextRenderer::class, $renderer);
    }
}
