<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\MetaBox;

use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MetaBoxTabSectionsTest extends TestCase
{
    /** @return array{hasTabs: bool, beforeTabs: list<mixed>, sections: list<array{key: string, label: string, fields: list<mixed>}>} */
    private function sections(array $fields): array
    {
        $method = new ReflectionMethod(MetaBoxRenderer::class, 'extractTabSections');

        return $method->invoke((new \ReflectionClass(MetaBoxRenderer::class))->newInstanceWithoutConstructor(), $fields);
    }

    public function test_each_tab_keeps_its_own_label_and_fields(): void
    {
        $info = $this->sections([
            Field::text('intro'),
            Field::tab('tab_a')->label('Tab A'),
            Field::text('a1'),
            Field::text('a2'),
            Field::tab('tab_b')->label('Tab B'),
            Field::text('b1'),
            Field::tab('tab_c')->label('Tab C'),
            Field::text('c1'),
        ]);

        $this->assertTrue($info['hasTabs']);
        $this->assertSame(['intro'], array_map(fn ($f) => $f->getKey(), $info['beforeTabs']));
        $this->assertSame(['Tab A', 'Tab B', 'Tab C'], array_column($info['sections'], 'label'));
        $this->assertSame(
            [['a1', 'a2'], ['b1'], ['c1']],
            array_map(fn ($s) => array_map(fn ($f) => $f->getKey(), $s['fields']), $info['sections'])
        );
    }

    public function test_group_without_tabs(): void
    {
        $info = $this->sections([Field::text('x'), Field::text('y')]);

        $this->assertFalse($info['hasTabs']);
        $this->assertSame([], $info['sections']);
        $this->assertCount(2, $info['beforeTabs']);
    }
}
