<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Builder;

use CtrlField\Builder\Taxonomy;
use CtrlField\Fields\Field;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxonomyBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        Taxonomy::reset();
    }

    public function test_make_returns_instance(): void
    {
        $tax = Taxonomy::make('project_category');

        $this->assertInstanceOf(Taxonomy::class, $tax);
        $this->assertSame('project_category', $tax->getTaxonomy());
    }

    public function test_label_sets_singular_and_plural(): void
    {
        $tax = Taxonomy::make('cat')->label('Category', 'Categories');

        $this->assertSame('Category', $tax->getSingularLabel());
        $this->assertSame('Categories', $tax->getPluralLabel());
    }

    public function test_hierarchical_defaults_to_false(): void
    {
        $this->assertFalse(Taxonomy::make('tag')->isHierarchical());
    }

    public function test_hierarchical_true(): void
    {
        $tax = Taxonomy::make('cat')->hierarchical(true);

        $this->assertTrue($tax->isHierarchical());
    }

    public function test_attach_to_stores_post_types(): void
    {
        $tax = Taxonomy::make('cat')->attachTo(['portfolio', 'service']);

        $this->assertSame(['portfolio', 'service'], $tax->getAttachTo());
    }

    public function test_register_stores_in_registry(): void
    {
        Taxonomy::make('project_category')
            ->label('Category', 'Categories')
            ->attachTo(['portfolio'])
            ->register();

        $this->assertTrue(Taxonomy::has('project_category'));
    }

    public function test_all_returns_registered_taxonomies(): void
    {
        Taxonomy::make('cat')->register();
        Taxonomy::make('tag')->register();

        $this->assertCount(2, Taxonomy::all());
    }

    public function test_reset_clears_registry(): void
    {
        Taxonomy::make('cat')->register();
        Taxonomy::reset();

        $this->assertEmpty(Taxonomy::all());
    }

    public function test_register_duplicate_key_silently_overwrites(): void
    {
        Taxonomy::make('cat')->label('Category', 'Categories')->register();
        Taxonomy::make('cat')->label('Tag', 'Tags')->register();

        $this->assertSame('Tag', Taxonomy::all()['cat']->getSingularLabel());
        $this->assertCount(1, Taxonomy::all());
    }

    public function test_fluent_returns_same_instance(): void
    {
        $tax = Taxonomy::make('t');

        $this->assertSame($tax, $tax->label('A', 'B'));
        $this->assertSame($tax, $tax->attachTo(['post']));
        $this->assertSame($tax, $tax->hierarchical());
    }

    public function test_term_fields_accepts_text_image_select(): void
    {
        $tax = Taxonomy::make('cat')->termFields([
            Field::text('bio')->label('Bio'),
            Field::image('photo')->label('Photo'),
            Field::select('status')->label('Status')->options(['active' => 'Active']),
        ]);

        $this->assertCount(3, $tax->getTermFields());
    }

    public function test_term_fields_rejects_unsupported_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("type 'textarea'");

        Taxonomy::make('cat')->termFields([
            Field::textarea('bio'),
        ]);
    }

    public function test_term_fields_empty_by_default(): void
    {
        $this->assertSame([], Taxonomy::make('cat')->getTermFields());
    }
}
