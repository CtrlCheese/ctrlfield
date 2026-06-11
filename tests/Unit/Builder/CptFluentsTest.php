<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Builder;

use FieldForge\Builder\CPT;
use PHPUnit\Framework\TestCase;

class CptFluentsTest extends TestCase
{
    protected function setUp(): void
    {
        CPT::reset();
    }

    public function test_show_in_rest_defaults_true(): void
    {
        $this->assertTrue(CPT::make('portfolio')->isShowInRest());
    }

    public function test_show_in_rest_can_be_disabled(): void
    {
        $this->assertFalse(CPT::make('portfolio')->showInRest(false)->isShowInRest());
    }

    public function test_has_archive_defaults_false(): void
    {
        $this->assertFalse(CPT::make('portfolio')->getHasArchive());
    }

    public function test_has_archive_enabled(): void
    {
        $this->assertTrue(CPT::make('portfolio')->hasArchive(true)->getHasArchive());
    }

    public function test_has_archive_custom_slug(): void
    {
        $this->assertSame('projects', CPT::make('portfolio')->hasArchive('projects')->getHasArchive());
    }

    public function test_rewrite_slug(): void
    {
        $this->assertSame('work', CPT::make('portfolio')->rewriteSlug('work')->getRewriteSlug());
    }

    public function test_rewrite_slug_empty_by_default(): void
    {
        $this->assertSame('', CPT::make('portfolio')->getRewriteSlug());
    }

    public function test_menu_position(): void
    {
        $this->assertSame(5, CPT::make('portfolio')->menuPosition(5)->getMenuPosition());
    }

    public function test_menu_position_null_by_default(): void
    {
        $this->assertNull(CPT::make('portfolio')->getMenuPosition());
    }

    public function test_description(): void
    {
        $this->assertSame('My CPT', CPT::make('portfolio')->description('My CPT')->getDescription());
    }

    public function test_public_defaults_true(): void
    {
        $this->assertTrue(CPT::make('portfolio')->isPublic());
    }

    public function test_public_can_be_disabled(): void
    {
        $this->assertFalse(CPT::make('portfolio')->public(false)->isPublic());
    }

    public function test_capability_type_defaults_post(): void
    {
        $this->assertSame('post', CPT::make('portfolio')->getCapabilityType());
    }

    public function test_capability_type_page(): void
    {
        $this->assertSame('page', CPT::make('portfolio')->capability('page')->getCapabilityType());
    }
    // New params
    public function test_hierarchical_defaults_false(): void
    {
        $this->assertFalse(CPT::make('portfolio')->isHierarchical());
    }

    public function test_hierarchical_can_be_enabled(): void
    {
        $this->assertTrue(CPT::make('portfolio')->hierarchical()->isHierarchical());
    }

    public function test_exclude_from_search_defaults_false(): void
    {
        $this->assertFalse(CPT::make('portfolio')->isExcludeFromSearch());
    }

    public function test_exclude_from_search_can_be_enabled(): void
    {
        $this->assertTrue(CPT::make('portfolio')->excludeFromSearch()->isExcludeFromSearch());
    }

    public function test_publicly_queryable_defaults_true(): void
    {
        $this->assertTrue(CPT::make('portfolio')->isPubliclyQueryable());
    }

    public function test_publicly_queryable_can_be_disabled(): void
    {
        $this->assertFalse(CPT::make('portfolio')->publiclyQueryable(false)->isPubliclyQueryable());
    }

    public function test_show_in_menu_defaults_true(): void
    {
        $this->assertTrue(CPT::make('portfolio')->getShowInMenu());
    }

    public function test_show_in_menu_string_value(): void
    {
        $this->assertSame('tools.php', CPT::make('portfolio')->showInMenu('tools.php')->getShowInMenu());
    }

    public function test_can_export_defaults_true(): void
    {
        $this->assertTrue(CPT::make('portfolio')->canExportPosts());
    }

    public function test_can_export_can_be_disabled(): void
    {
        $this->assertFalse(CPT::make('portfolio')->canExport(false)->canExportPosts());
    }
}
