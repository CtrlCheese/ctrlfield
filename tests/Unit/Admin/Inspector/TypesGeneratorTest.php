<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\Inspector;

use CtrlField\Admin\Inspector\InspectorData;
use CtrlField\Admin\Inspector\SchemaInspectorRenderer;
use CtrlField\Admin\Inspector\TypesGenerator;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

final class TypesGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    private function group(): FieldGroup
    {
        return FieldGroup::make('hero_section')->title('Hero')->fields([
            Field::tab('main')->label('Main'),
            Field::text('title'),
            Field::image('bg')->returnFormat('array'),
            Field::link('buttons')->multiple(),
            Field::number('count'),
            Field::trueFalse('dark'),
            Field::object('meta')->fields([Field::text('a'), Field::checkbox('tags')]),
        ]);
    }

    public function test_php_shape_follows_types_return_formats_and_nesting(): void
    {
        $php = (new TypesGenerator())->php($this->group());

        $this->assertStringContainsString(" *     title: string,\n", $php);
        $this->assertStringContainsString('bg: array{id: int, url: string, width: int, height: int, alt: string}|null', $php);
        $this->assertStringContainsString('buttons: list<array{url: string', $php);
        $this->assertStringContainsString('count: int|float|null', $php);
        $this->assertStringContainsString('dark: bool', $php);
        $this->assertStringContainsString(" *     meta: array{\n *         a: string,\n *         tags: list<string>\n *     }\n * } \$data", $php);
        $this->assertStringNotContainsString('main', $php, 'tabs hold no value');
    }

    public function test_typescript_interface(): void
    {
        $ts = (new TypesGenerator())->typescript($this->group());

        $this->assertStringContainsString('export interface HeroSection {', $ts);
        $this->assertStringContainsString('  dark: boolean;', $ts);
        $this->assertStringContainsString("  meta: {\n    a: string;\n    tags: string[];\n  };", $ts);
    }

    public function test_interface_name(): void
    {
        $this->assertSame('ProjectFreeFields', TypesGenerator::interfaceName('project_free_fields'));
        $this->assertSame('Group404Page', TypesGenerator::interfaceName('404-page'));
    }

    public function test_filled_ignores_empty_values_and_layout_names(): void
    {
        $this->assertTrue(InspectorData::filled('x'));
        $this->assertTrue(InspectorData::filled(0));
        $this->assertFalse(InspectorData::filled(''));
        $this->assertFalse(InspectorData::filled(false));
        $this->assertFalse(InspectorData::filled(['a' => '', 'b' => null]));
        $this->assertFalse(InspectorData::filled([['_layout' => 'hero']]));
        $this->assertTrue(InspectorData::filled([['_layout' => 'hero', 'title' => 'Hi']]));
    }

    public function test_layouts_found_at_any_depth(): void
    {
        $fields = ['sections' => [['_layout' => 'hero'], ['_layout' => 'tabs', 'items' => [['_layout' => 'card']]]]];

        $this->assertSame(['hero', 'tabs', 'card'], array_keys(InspectorData::layouts($fields)));
    }

    public function test_value_preview(): void
    {
        $this->assertSame('""', SchemaInspectorRenderer::preview(''));
        $this->assertSame("{\n    \"url\": \"/a/b\"\n}", SchemaInspectorRenderer::preview(['url' => '/a/b']));
        $this->assertStringStartsWith('DateTimeImmutable 2026-10-13', SchemaInspectorRenderer::preview(new \DateTimeImmutable('2026-10-13')));
    }
}
