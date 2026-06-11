<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Builder;

use FieldForge\Builder\BlockConfig;
use FieldForge\Builder\Exceptions\MissingBlockRendererException;
use FieldForge\Builder\FieldGroup;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class FieldGroupBlockTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_is_block_defaults_false(): void
    {
        $group = FieldGroup::make('hero');
        $this->assertFalse($group->isBlock());
        $this->assertNull($group->getBlockConfig());
    }

    public function test_as_block_with_render_callback(): void
    {
        $group = FieldGroup::make('hero')
            ->asBlock(
                icon:           'cover-image',
                category:       'design',
                keywords:       ['hero', 'banner'],
                renderCallback: static fn(array $attrs): string => '<h1>' . ($attrs['title'] ?? '') . '</h1>',
            );

        $this->assertTrue($group->isBlock());
        $config = $group->getBlockConfig();
        $this->assertInstanceOf(BlockConfig::class, $config);
        $this->assertSame('cover-image', $config->icon);
        $this->assertSame('design', $config->category);
        $this->assertSame(['hero', 'banner'], $config->keywords);
        $this->assertIsCallable($config->renderCallback);
    }

    public function test_as_block_with_render_template(): void
    {
        $group = FieldGroup::make('hero')
            ->asBlock(renderTemplate: 'blocks/hero.blade.php');

        $this->assertTrue($group->isBlock());
        $this->assertSame('blocks/hero.blade.php', $group->getBlockConfig()->renderTemplate);
    }

    public function test_as_block_without_renderer_throws(): void
    {
        $this->expectException(MissingBlockRendererException::class);

        FieldGroup::make('hero')->asBlock();
    }

    public function test_as_block_returns_fluent_instance(): void
    {
        $group = FieldGroup::make('hero');
        $result = $group->asBlock(renderCallback: fn($a) => '');
        $this->assertSame($group, $result);
    }

    public function test_render_callback_is_invocable(): void
    {
        $group = FieldGroup::make('hero')
            ->asBlock(renderCallback: static fn(array $attrs): string => 'Hello ' . ($attrs['name'] ?? ''));

        $result = ($group->getBlockConfig()->renderCallback)(['name' => 'World']);
        $this->assertSame('Hello World', $result);
    }
}
