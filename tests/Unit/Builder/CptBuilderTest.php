<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Builder;

use CtrlField\Builder\CPT;
use PHPUnit\Framework\TestCase;

class CptBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        CPT::reset();
    }

    public function test_make_returns_instance(): void
    {
        $cpt = CPT::make('portfolio');

        $this->assertInstanceOf(CPT::class, $cpt);
        $this->assertSame('portfolio', $cpt->getPostType());
    }

    public function test_label_sets_singular_and_plural(): void
    {
        $cpt = CPT::make('portfolio')->label('Portfolio', 'Projects');

        $this->assertSame('Portfolio', $cpt->getSingularLabel());
        $this->assertSame('Projects', $cpt->getPluralLabel());
    }

    public function test_menu_icon_default(): void
    {
        $this->assertSame('dashicons-admin-post', CPT::make('p')->getMenuIcon());
    }

    public function test_menu_icon_custom(): void
    {
        $cpt = CPT::make('portfolio')->menuIcon('dashicons-portfolio');

        $this->assertSame('dashicons-portfolio', $cpt->getMenuIcon());
    }

    public function test_supports_default(): void
    {
        $this->assertSame(['title', 'editor'], CPT::make('p')->getSupports());
    }

    public function test_supports_custom(): void
    {
        $cpt = CPT::make('portfolio')->supports(['title', 'thumbnail', 'excerpt']);

        $this->assertSame(['title', 'thumbnail', 'excerpt'], $cpt->getSupports());
    }

    public function test_register_stores_in_registry(): void
    {
        CPT::make('portfolio')->label('Portfolio', 'Projects')->register();

        $this->assertTrue(CPT::has('portfolio'));
        $this->assertSame('portfolio', CPT::all()['portfolio']->getPostType());
    }

    public function test_all_returns_all_registered_cpts(): void
    {
        CPT::make('portfolio')->register();
        CPT::make('service')->register();

        $this->assertCount(2, CPT::all());
    }

    public function test_reset_clears_registry(): void
    {
        CPT::make('portfolio')->register();
        CPT::reset();

        $this->assertEmpty(CPT::all());
    }

    public function test_register_duplicate_key_silently_overwrites(): void
    {
        // CPT::register() silently overwrites on duplicate key (unlike FieldGroup which throws).
        // This is intentional: a CPT definition may be overridden by child themes or plugins.
        CPT::make('portfolio')->label('Portfolio', 'Projects')->register();
        CPT::make('portfolio')->label('Portfolio V2', 'Projects V2')->register();

        $this->assertSame('Portfolio V2', CPT::all()['portfolio']->getSingularLabel());
        $this->assertCount(1, CPT::all());
    }

    public function test_fluent_returns_same_instance(): void
    {
        $cpt = CPT::make('p');

        $this->assertSame($cpt, $cpt->label('A', 'B'));
        $this->assertSame($cpt, $cpt->menuIcon('icon'));
        $this->assertSame($cpt, $cpt->supports(['title']));
    }
}
