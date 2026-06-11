<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Builder;

use FieldForge\Builder\Taxonomy;
use PHPUnit\Framework\TestCase;

class TaxonomyFluentsTest extends TestCase
{
    protected function setUp(): void
    {
        Taxonomy::reset();
    }

    public function test_show_in_rest_defaults_true(): void
    {
        $this->assertTrue(Taxonomy::make('category')->isShowInRest());
    }

    public function test_show_in_rest_can_be_disabled(): void
    {
        $this->assertFalse(Taxonomy::make('category')->showInRest(false)->isShowInRest());
    }

    public function test_rewrite_slug(): void
    {
        $this->assertSame('cats', Taxonomy::make('category')->rewriteSlug('cats')->getRewriteSlug());
    }

    public function test_rewrite_slug_empty_by_default(): void
    {
        $this->assertSame('', Taxonomy::make('category')->getRewriteSlug());
    }

    public function test_show_in_nav_menus_defaults_true(): void
    {
        $this->assertTrue(Taxonomy::make('category')->isShowInNavMenus());
    }

    public function test_show_in_nav_menus_can_be_disabled(): void
    {
        $this->assertFalse(Taxonomy::make('category')->showInNavMenus(false)->isShowInNavMenus());
    }

    public function test_show_tag_cloud_defaults_true(): void
    {
        $this->assertTrue(Taxonomy::make('category')->isShowTagCloud());
    }

    public function test_show_tag_cloud_can_be_disabled(): void
    {
        $this->assertFalse(Taxonomy::make('category')->showTagCloud(false)->isShowTagCloud());
    }

    public function test_description(): void
    {
        $this->assertSame('Blog categories', Taxonomy::make('category')->description('Blog categories')->getDescription());
    }
}
