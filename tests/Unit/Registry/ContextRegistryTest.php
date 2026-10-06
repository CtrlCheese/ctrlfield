<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Builder\AdminContext;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class ContextRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    public function test_resolves_group_by_post_type(): void
    {
        FieldGroup::make('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('client')])
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(postType: 'portfolio'));

        $this->assertCount(1, $matches);
        $this->assertArrayHasKey('portfolio_details', $matches);
    }

    public function test_does_not_resolve_group_for_wrong_post_type(): void
    {
        FieldGroup::make('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(postType: 'page'));

        $this->assertEmpty($matches);
    }

    public function test_resolves_group_by_options_page(): void
    {
        FieldGroup::make('theme_fields')
            ->where('options_page', '==', 'theme_settings')
            ->fields([Field::text('tagline')])
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(optionsPage: 'theme_settings'));

        $this->assertCount(1, $matches);
        $this->assertArrayHasKey('theme_fields', $matches);
    }

    public function test_does_not_match_options_page_group_on_post_context(): void
    {
        FieldGroup::make('theme_fields')
            ->where('options_page', '==', 'theme_settings')
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(postType: 'portfolio'));

        $this->assertEmpty($matches);
    }

    public function test_resolves_multiple_groups_for_same_context(): void
    {
        FieldGroup::make('group_a')->where('post_type', '==', 'portfolio')->register();
        FieldGroup::make('group_b')->where('post_type', '==', 'portfolio')->register();
        FieldGroup::make('group_c')->where('post_type', '==', 'service')->register();

        $matches = ContextRegistry::resolve(new AdminContext(postType: 'portfolio'));

        $this->assertCount(2, $matches);
        $this->assertArrayHasKey('group_a', $matches);
        $this->assertArrayHasKey('group_b', $matches);
        $this->assertArrayNotHasKey('group_c', $matches);
    }

    public function test_empty_context_resolves_nothing(): void
    {
        FieldGroup::make('g')->where('post_type', '==', 'portfolio')->register();

        $matches = ContextRegistry::resolve(new AdminContext());

        $this->assertEmpty($matches);
    }

    public function test_resolves_group_with_where_any(): void
    {
        FieldGroup::make('multi_cpt')->whereAny([
            ['post_type', '==', 'portfolio'],
            ['post_type', '==', 'service'],
        ])->register();

        $this->assertCount(1, ContextRegistry::resolve(new AdminContext(postType: 'portfolio')));
        $this->assertCount(1, ContextRegistry::resolve(new AdminContext(postType: 'service')));
        $this->assertEmpty(ContextRegistry::resolve(new AdminContext(postType: 'page')));
    }
}
