<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Compat;

use CtrlField\Compat\Acf\AcfValues;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

final class AcfValuesTest extends TestCase
{
    /** @param array<string, mixed> $meta */
    private function getter(array $meta): callable
    {
        return static fn (string $name): mixed => $meta[$name] ?? null;
    }

    public function test_reads_scalars_and_missing_values(): void
    {
        $get = $this->getter(['title' => 'Hello', 'empty' => '']);

        $this->assertSame([true, 'Hello'], AcfValues::readMeta(['type' => 'text'], $get, 'title'));
        $this->assertSame([true, ''], AcfValues::readMeta(['type' => 'text'], $get, 'empty'));
        $this->assertSame([false, null], AcfValues::readMeta(['type' => 'text'], $get, 'missing'));
    }

    public function test_reads_repeaters_groups_and_layouts(): void
    {
        $get = $this->getter([
            'team' => '2', 'team_0_name' => 'Ana', 'team_1_name' => 'Bruno', 'team_1_Job-Title' => 'Dev',
            'address_city' => 'Berlin',
            'sections' => ['hero', 'gone', 'quote'], 'sections_0_title' => 'Hi', 'sections_2_text' => 'Less',
        ]);
        $repeater = ['type' => 'repeater', 'sub_fields' => [['name' => 'name', 'type' => 'text'], ['name' => 'Job-Title', 'type' => 'text']]];
        $group    = ['type' => 'group', 'sub_fields' => [['name' => 'street', 'type' => 'text'], ['name' => 'city', 'type' => 'text']]];
        $flex     = ['type' => 'flexible_content', 'layouts' => [
            ['name' => 'hero', 'sub_fields' => [['name' => 'title', 'type' => 'text']]],
            ['name' => 'quote', 'sub_fields' => [['name' => 'text', 'type' => 'text']]],
        ]];

        $this->assertSame([true, [['name' => 'Ana'], ['name' => 'Bruno', 'job_title' => 'Dev']]], AcfValues::readMeta($repeater, $get, 'team'));
        $this->assertSame([true, ['city' => 'Berlin']], AcfValues::readMeta($group, $get, 'address'));
        $this->assertSame([false, []], AcfValues::readMeta($group, $get, 'nothing'));
        $this->assertSame(
            [true, [['acf_fc_layout' => 'hero', 'title' => 'Hi'], ['acf_fc_layout' => 'quote', 'text' => 'Less']]],
            AcfValues::readMeta($flex, $get, 'sections'),
            'the layout key of a later row is kept (sections_2_text), unknown layouts are dropped'
        );
    }

    public function test_converts_acf_values_to_storage(): void
    {
        $this->assertSame('2026-12-31', AcfValues::toStorage('20261231', Field::date('d')));
        $this->assertSame('2026-12-31', AcfValues::toStorage('2026-12-31', Field::date('d')));
        $this->assertSame('2026-12-31T14:30:00', AcfValues::toStorage('2026-12-31 14:30:00', Field::datetime('dt')));
        $this->assertSame('09:05', AcfValues::toStorage('09:05:00', Field::time('t')));
        $this->assertFalse(AcfValues::toStorage('0', Field::trueFalse('b')));
        $this->assertTrue(AcfValues::toStorage('1', Field::trueFalse('b')));
        $this->assertSame(26, AcfValues::toStorage(['ID' => 26, 'url' => 'x'], Field::image('i')));
        $this->assertSame(['a'], AcfValues::toStorage('a', Field::checkbox('c')));
        $this->assertSame(['33', '34'], array_map('strval', AcfValues::toStorage(['33', '34'], Field::relationship('r'))));
        $this->assertSame(5, AcfValues::toStorage(['5'], Field::postObject('p')), 'single post object from a list');
        $this->assertSame([5, 6], AcfValues::toStorage(['5', 6], Field::postObject('p')->multiple()));
        $this->assertSame(['url' => 'https://x.test', 'title' => '', 'target' => ''], AcfValues::toStorage('https://x.test', Field::link('l')));
    }

    public function test_nested_values_follow_their_definitions(): void
    {
        $box = Field::object('box')->fields([Field::date('when'), Field::text('note')]);

        $this->assertSame(
            ['when' => '2027-01-15', 'note' => 'x'],
            AcfValues::toStorage(['when' => '20270115', 'note' => 'x', 'junk' => 1], $box)
        );
    }
}
