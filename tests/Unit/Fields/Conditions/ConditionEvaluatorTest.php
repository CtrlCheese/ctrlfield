<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Conditions;

use CtrlField\Fields\Conditions\ConditionEvaluator;
use CtrlField\Fields\Conditions\ConditionGroup;
use PHPUnit\Framework\TestCase;

/** Server twin of conditionEvaluator.js (C7.1) — same answers as the editor. */
class ConditionEvaluatorTest extends TestCase
{
    private static function all(string $field, string $op, mixed $value): ConditionGroup
    {
        return ConditionGroup::all([[$field, $op, $value]]);
    }

    public function test_reads_the_row_first(): void
    {
        $top = ['layout' => 'grid'];
        self::assertTrue(ConditionEvaluator::isVisible(self::all('layout', '==', 'list'), [['layout' => 'list'], $top]));
        self::assertFalse(ConditionEvaluator::isVisible(self::all('layout', '==', 'grid'), [['layout' => 'list'], $top]));
    }

    public function test_falls_back_to_outer_scopes(): void
    {
        $scopes = [['label' => 'x'], ['variant' => 'b'], ['mode' => 'top']];
        self::assertTrue(ConditionEvaluator::isVisible(self::all('variant', '==', 'b'), $scopes));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('mode', '==', 'top'), $scopes));
    }

    public function test_key_with_empty_value_does_not_fall_through(): void
    {
        self::assertTrue(ConditionEvaluator::isVisible(self::all('layout', 'empty', null), [['layout' => ''], ['layout' => 'grid']]));
    }

    public function test_js_like_loose_equality(): void
    {
        self::assertTrue(ConditionEvaluator::isVisible(self::all('on', '==', 1), [['on' => true]]));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('on', '==', '1'), [['on' => true]]));
        self::assertFalse(ConditionEvaluator::isVisible(self::all('on', '==', 'yes'), [['on' => true]]));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('n', '==', 3), [['n' => '3']]));
        self::assertFalse(ConditionEvaluator::isVisible(self::all('s', '==', '1.0'), [['s' => '1']])); // two strings: text
        self::assertTrue(ConditionEvaluator::isVisible(self::all('missing', '==', ''), [[]]));
    }

    public function test_empty_matches_js(): void
    {
        self::assertTrue(ConditionEvaluator::isVisible(self::all('a', 'empty', null), [['a' => []]]));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('a', 'empty', null), [['a' => false]]));
        self::assertFalse(ConditionEvaluator::isVisible(self::all('a', 'empty', null), [['a' => '0']])); // non-empty string in JS
    }

    public function test_in_and_any(): void
    {
        self::assertTrue(ConditionEvaluator::isVisible(self::all('k', 'in', ['a', 'b']), [['k' => 'b']]));
        self::assertFalse(ConditionEvaluator::isVisible(self::all('k', 'not_in', ['a', 'b']), [['k' => 'b']]));
        $any = ConditionGroup::any([['k', '==', 'z'], ['k', '==', 'a']]);
        self::assertTrue(ConditionEvaluator::isVisible($any, [['k' => 'a']]));
    }

    public function test_ordered_comparisons(): void
    {
        self::assertTrue(ConditionEvaluator::isVisible(self::all('n', '>', 5), [['n' => '7']]));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('t', '<', '10:30'), [['t' => '09:15']]));
        self::assertTrue(ConditionEvaluator::isVisible(self::all('d', '>=', '2026-01-01'), [['d' => '2026-10-11']]));
    }
}
