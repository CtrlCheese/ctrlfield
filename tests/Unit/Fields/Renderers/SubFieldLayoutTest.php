<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Renderers;

use CtrlField\Fields\Field;
use CtrlField\Fields\Renderers\AbstractRenderer;
use CtrlField\Fields\Renderers\FieldLayout;
use CtrlField\Fields\Renderers\GroupRenderer;
use CtrlField\Fields\Renderers\SubFieldTable;
use PHPUnit\Framework\TestCase;

/** ->width() at any level, ->layout('row'|'table') and ->placement('left'). */
final class SubFieldLayoutTest extends TestCase
{
    public function test_width_becomes_a_css_variable(): void
    {
        $html = AbstractRenderer::fieldHtml(Field::text('a')->width(33.33), "row['a']");

        $this->assertStringContainsString('class="ctrlf-field ctrlf-col" style="--ctrlf-w:33.33"', $html);
        $this->assertStringContainsString('class="ctrlf-field"', AbstractRenderer::fieldHtml(Field::text('b'), "row['b']"));
    }

    public function test_group_layouts(): void
    {
        $fields = [Field::text('a')->label('A'), Field::text('b')->label('B')];

        $block = (new GroupRenderer())->render(Field::object('g')->fields($fields), "s['g']");
        $row   = (new GroupRenderer())->render(Field::object('g')->layout('row')->fields($fields), "s['g']");
        $table = (new GroupRenderer())->render(Field::object('g')->layout('table')->fields($fields), "s['g']");

        $this->assertStringStartsWith('<div class="ctrlf-group">', $block);
        $this->assertStringStartsWith('<div class="ctrlf-group ctrlf-layout-row">', $row);
        $this->assertStringContainsString('ctrlf-table__head', $table);
        $this->assertSame(2, substr_count($table, 'role="columnheader"'));
        $this->assertSame(2, substr_count($table, 'class="ctrlf-table__td"'));
    }

    public function test_unknown_layout_falls_back_to_block(): void
    {
        $this->assertSame('block', Field::object('g')->layout('grid')->getLayout());
    }

    public function test_table_columns_share_what_widths_leave(): void
    {
        $cols = SubFieldTable::columns([
            Field::text('a')->width(50),
            Field::tab('t'),               // no column for tabs
            Field::text('b'),
            Field::text('c'),
        ]);

        $this->assertCount(3, $cols);
        $this->assertSame('minmax(0, 50fr) minmax(0, 25fr) minmax(0, 25fr)', SubFieldTable::template($cols));
    }

    public function test_first_tab_sets_left_placement(): void
    {
        $html = FieldLayout::render(
            [Field::tab('one')->placement('left'), Field::text('a'), Field::tab('two'), Field::text('b')],
            static fn ($f): string => '<i>' . $f->getKey() . '</i>'
        );

        $this->assertStringContainsString('class="ctrlf-tabs ctrlf-tabs--left"', $html);
        $this->assertStringContainsString('aria-orientation="vertical"', $html);
        $this->assertStringNotContainsString('ctrlf-tabs--left', FieldLayout::render(
            [Field::tab('one'), Field::text('a')],
            static fn ($f): string => ''
        ));
    }

    public function test_horizontal_accordions_render_as_one_set(): void
    {
        $html = FieldLayout::render([
            Field::accordion('a')->label('A')->style('horizontal')->open(false),
            Field::text('x'),
            Field::accordion('b')->label('B')->open(),
            Field::text('y'),
            Field::accordionEnd('end'),
            Field::text('after'),
        ], static fn ($f): string => '<i>' . $f->getKey() . '</i>');

        $this->assertSame(1, substr_count($html, 'ctrlf-accset ctrlf-accset--horizontal'));
        $this->assertSame(2, substr_count($html, 'class="ctrlf-accset__item"'));
        $this->assertStringContainsString("acc: \$ctrlfUi('accset:a', 1)", $html); // B starts open
        $this->assertStringNotContainsString('ctrlf-accordion"', $html);
        $this->assertStringEndsWith('<i>after</i>', $html);
    }

    public function test_side_accordions_list_titles_beside_the_panels(): void
    {
        $html = FieldLayout::render([
            Field::accordion('a')->label('A')->style('side'),
            Field::text('x'),
            Field::accordion('b')->label('B'),
            Field::text('y'),
        ], static fn ($f): string => '<i>' . $f->getKey() . '</i>');

        $this->assertStringContainsString('<div class="ctrlf-accset__list">', $html);
        $this->assertSame(2, substr_count($html, 'class="ctrlf-accset__toggle"'));
        $this->assertSame(2, substr_count($html, 'class="ctrlf-accset__content"'));
    }

    public function test_stacked_accordions_are_unchanged(): void
    {
        $html = FieldLayout::render([Field::accordion('a'), Field::text('x')], static fn ($f): string => '');

        $this->assertStringContainsString('class="ctrlf-accordion"', $html);
        $this->assertStringNotContainsString('ctrlf-accset', $html);
    }
}
