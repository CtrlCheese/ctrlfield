<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields;

use CtrlField\Fields\Field;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Renderers\FieldLayout;
use PHPUnit\Framework\TestCase;

/** Accordions and tabs, laid out the way ACF does at any level. */
final class FieldLayoutTest extends TestCase
{
    /** @return list<string> */
    private function keys(array $fields): array
    {
        return array_map(static fn (FieldDefinition $f) => $f->getKey(), $fields);
    }

    public function test_each_tab_keeps_its_own_label_and_fields(): void
    {
        // A version that kept a PHP reference to the open section overwrote
        // every earlier tab: all tabs showed the last label and its fields.
        [$section] = FieldLayout::sections([
            Field::text('intro'),
            Field::tab('tab_a')->label('Tab A'), Field::text('a1'), Field::text('a2'),
            Field::tab('tab_b')->label('Tab B'), Field::text('b1'),
            Field::tab('tab_c')->label('Tab C'), Field::text('c1'),
        ]);

        $this->assertNull($section['accordion']);
        $this->assertSame(['intro'], $this->keys($section['before']));
        $this->assertSame(['tab_a', 'tab_b', 'tab_c'], array_map(static fn ($t) => $t['tab']->getKey(), $section['tabs']));
        $this->assertSame([['a1', 'a2'], ['b1'], ['c1']], array_map(fn ($t) => $this->keys($t['fields']), $section['tabs']));
    }

    public function test_fields_without_tabs_or_accordions(): void
    {
        $sections = FieldLayout::sections([Field::text('a'), Field::text('b')]);

        $this->assertCount(1, $sections);
        $this->assertSame(['a', 'b'], $this->keys($sections[0]['before']));
        $this->assertSame([], $sections[0]['tabs']);
    }

    public function test_accordions_run_until_the_next_one_and_scope_their_tabs(): void
    {
        // Flynt options: one accordion per component, each with its own tabs.
        $sections = FieldLayout::sections([
            Field::text('top'),
            Field::accordion('acc_1')->label('Slider'),
            Field::tab('content_a'), Field::text('a'),
            Field::tab('labels_a'), Field::text('b'),
            Field::accordion('acc_2')->label('Footer'),
            Field::tab('content_b'), Field::text('c'),
            Field::accordionEnd('end'),
            Field::text('after'),
        ]);

        $this->assertCount(4, $sections);
        $this->assertSame(['top'], $this->keys($sections[0]['before']));
        $this->assertSame('acc_1', $sections[1]['accordion']->getKey());
        $this->assertCount(2, $sections[1]['tabs']);
        $this->assertSame('acc_2', $sections[2]['accordion']->getKey());
        $this->assertCount(1, $sections[2]['tabs']);
        $this->assertNull($sections[3]['accordion']);
        $this->assertSame(['after'], $this->keys($sections[3]['before']));
    }

    public function test_rendered_markup_is_balanced_and_scoped(): void
    {
        $html = FieldLayout::render([
            Field::accordion('acc')->label('Section')->open(false),
            Field::tab('contentTab')->label('Content'), Field::text('a'),
            Field::tab('contentTab')->label('Again'), Field::text('b'),
        ], static fn (FieldDefinition $f): string => '<i>' . $f->getKey() . '</i>');

        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
        $this->assertStringContainsString("open: \$ctrlfUi('acc:acc', false)", $html, 'closed by default, remembered per browser');
        $this->assertStringContainsString("\$ctrlfUiSet('acc:acc', open)", $html);
        $this->assertStringContainsString("ctrlfTab: \$ctrlfUi('tab:contentTab', 'contentTab_0')", $html);
        $this->assertStringContainsString("ctrlfTab === 'contentTab_1'", $html, 'repeated tab keys stay distinct');
        $this->assertStringContainsString('<i>a</i>', $html);
        $this->assertStringNotContainsString('<i>contentTab</i>', $html, 'tabs are not rendered as fields');
    }
}
