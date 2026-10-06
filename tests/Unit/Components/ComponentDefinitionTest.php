<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Components;

use CtrlField\Components\ComponentDefinition;
use PHPUnit\Framework\TestCase;

class ComponentDefinitionTest extends TestCase
{
    public function test_constructor_stores_all_properties(): void
    {
        $def = new ComponentDefinition(
            key:          'hero',
            path:         '/themes/my-theme/components/hero',
            label:        'Hero Banner',
            icon:         'cover-image',
            category:     'layout',
            templatePath: '/themes/my-theme/components/hero/index.blade.php',
            hasScript:    true,
            hasFunctions: false,
        );

        $this->assertSame('hero', $def->key);
        $this->assertSame('/themes/my-theme/components/hero', $def->path);
        $this->assertSame('Hero Banner', $def->label);
        $this->assertSame('cover-image', $def->icon);
        $this->assertSame('layout', $def->category);
        $this->assertSame('/themes/my-theme/components/hero/index.blade.php', $def->templatePath);
        $this->assertTrue($def->hasScript);
        $this->assertFalse($def->hasFunctions);
    }

    public function test_template_path_can_be_null(): void
    {
        $def = new ComponentDefinition(
            key:          'bare',
            path:         '/components/bare',
            label:        'Bare',
            icon:         '',
            category:     'general',
            templatePath: null,
            hasScript:    false,
            hasFunctions: false,
        );

        $this->assertNull($def->templatePath);
    }

    public function test_icon_can_be_empty(): void
    {
        $def = new ComponentDefinition(
            key:          'minimal',
            path:         '/components/minimal',
            label:        'Minimal',
            icon:         '',
            category:     'general',
            templatePath: null,
            hasScript:    false,
            hasFunctions: false,
        );

        $this->assertSame('', $def->icon);
    }

    public function test_properties_are_readonly(): void
    {
        $def = new ComponentDefinition(
            key:          'hero',
            path:         '/components/hero',
            label:        'Hero',
            icon:         '',
            category:     'general',
            templatePath: null,
            hasScript:    false,
            hasFunctions: false,
        );

        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line
        $def->key = 'modified';
    }
}
