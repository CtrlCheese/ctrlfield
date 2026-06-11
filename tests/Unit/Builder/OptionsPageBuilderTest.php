<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Builder;

use FieldForge\Builder\AdminContext;
use FieldForge\Builder\OptionsPage;
use FieldForge\Fields\Field;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class OptionsPageBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        OptionsPage::reset();
        FieldRegistry::reset();
    }

    public function test_make_returns_instance(): void
    {
        $page = OptionsPage::make('theme_settings');

        $this->assertInstanceOf(OptionsPage::class, $page);
        $this->assertSame('theme_settings', $page->getKey());
    }

    public function test_title_sets_title(): void
    {
        $page = OptionsPage::make('p')->title('Theme Settings');

        $this->assertSame('Theme Settings', $page->getTitle());
    }

    public function test_menu_slug_sets_slug(): void
    {
        $page = OptionsPage::make('p')->menuSlug('theme-settings');

        $this->assertSame('theme-settings', $page->getMenuSlug());
    }

    public function test_capability_defaults_to_manage_options(): void
    {
        $this->assertSame('manage_options', OptionsPage::make('p')->getCapability());
    }

    public function test_capability_custom(): void
    {
        $page = OptionsPage::make('p')->capability('edit_theme_options');

        $this->assertSame('edit_theme_options', $page->getCapability());
    }

    public function test_fields_stores_field_definitions(): void
    {
        $page = OptionsPage::make('p')->fields([
            Field::text('tagline'),
            Field::image('logo'),
        ]);

        $this->assertCount(2, $page->getFields());
    }

    public function test_register_stores_in_registry(): void
    {
        OptionsPage::make('theme_settings')->register();

        $this->assertTrue(OptionsPage::has('theme_settings'));
    }

    public function test_register_with_fields_creates_field_group(): void
    {
        OptionsPage::make('theme_settings')
            ->title('Theme Settings')
            ->fields([
                Field::text('tagline')->label('Tagline'),
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('_options_theme_settings'));
    }

    public function test_registered_field_group_resolves_via_context(): void
    {
        OptionsPage::make('theme_settings')
            ->fields([Field::text('tagline')])
            ->register();

        $matches = ContextRegistry::resolve(
            new AdminContext(optionsPage: 'theme_settings')
        );

        $this->assertCount(1, $matches);
        $this->assertArrayHasKey('_options_theme_settings', $matches);
    }

    public function test_register_without_fields_does_not_create_field_group(): void
    {
        OptionsPage::make('empty_page')->register();

        $this->assertFalse(FieldRegistry::has('_options_empty_page'));
    }

    public function test_all_returns_all_registered_pages(): void
    {
        OptionsPage::make('page_a')->register();
        OptionsPage::make('page_b')->register();

        $this->assertCount(2, OptionsPage::all());
    }

    public function test_reset_clears_registry(): void
    {
        OptionsPage::make('p')->register();
        OptionsPage::reset();

        $this->assertEmpty(OptionsPage::all());
    }

    public function test_register_called_twice_does_not_throw(): void
    {
        // Bug regression: second call must not throw DuplicateGroupKeyException
        // because OptionsPage silently overwrites itself in its own registry,
        // and the auto-created FieldGroup must not be re-registered.
        OptionsPage::make('theme_settings')
            ->fields([Field::text('tagline')])
            ->register();

        OptionsPage::make('theme_settings')
            ->fields([Field::text('tagline')])
            ->register(); // must not throw

        $this->assertTrue(OptionsPage::has('theme_settings'));
        $this->assertTrue(FieldRegistry::has('_options_theme_settings'));
    }

    public function test_parent_defaults_to_empty_string(): void
    {
        $this->assertSame('', OptionsPage::make('p')->getParent());
    }

    public function test_parent_sets_parent_slug(): void
    {
        $page = OptionsPage::make('p')->parent('options-general.php');

        $this->assertSame('options-general.php', $page->getParent());
    }

    public function test_icon_defaults_to_dashicons_admin_generic(): void
    {
        $this->assertSame('dashicons-admin-generic', OptionsPage::make('p')->getIcon());
    }

    public function test_icon_sets_custom_icon(): void
    {
        $page = OptionsPage::make('p')->icon('dashicons-art');

        $this->assertSame('dashicons-art', $page->getIcon());
    }

    public function test_position_defaults_to_80(): void
    {
        $this->assertSame(80, OptionsPage::make('p')->getPosition());
    }

    public function test_position_sets_custom_position(): void
    {
        $page = OptionsPage::make('p')->position(25);

        $this->assertSame(25, $page->getPosition());
    }

    public function test_fluent_returns_same_instance(): void
    {
        $page = OptionsPage::make('p');

        $this->assertSame($page, $page->title('T'));
        $this->assertSame($page, $page->menuSlug('slug'));
        $this->assertSame($page, $page->capability('manage_options'));
        $this->assertSame($page, $page->parent('options-general.php'));
        $this->assertSame($page, $page->icon('dashicons-art'));
        $this->assertSame($page, $page->position(20));
        $this->assertSame($page, $page->fields([]));
    }
}
