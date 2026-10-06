<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Conditions;

use CtrlField\Fields\Conditions\ConditionValidator;
use CtrlField\Fields\Exceptions\InvalidOperatorForTypeException;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class ConditionValidatorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // == / != — allowed on all types
    // -------------------------------------------------------------------------

    public function test_equals_allowed_on_text(): void
    {
        $source = Field::text('type');
        $target = Field::text('client')->visibleWhen('type', '==', 'external');
        ConditionValidator::validate($target, ['type' => $source]);
        $this->assertTrue(true); // no exception
    }

    public function test_not_equals_allowed_on_select(): void
    {
        $source = Field::select('tier')->options(['free' => 'Free', 'pro' => 'Pro']);
        $target = Field::text('feature')->visibleWhen('tier', '!=', 'free');
        ConditionValidator::validate($target, ['tier' => $source]);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // < <= > >= — numeric types only
    // -------------------------------------------------------------------------

    public function test_less_than_allowed_on_number(): void
    {
        $source = Field::number('score');
        $target = Field::text('msg')->visibleWhen('score', '<', 50);
        ConditionValidator::validate($target, ['score' => $source]);
        $this->assertTrue(true);
    }

    public function test_less_than_allowed_on_range(): void
    {
        $source = Field::range('volume')->min(0)->max(100);
        $target = Field::text('muted')->visibleWhen('volume', '<=', 10);
        ConditionValidator::validate($target, ['volume' => $source]);
        $this->assertTrue(true);
    }

    public function test_less_than_allowed_on_date(): void
    {
        $source = Field::date('deadline');
        $target = Field::text('overdue')->visibleWhen('deadline', '<', '2026-12-31');
        ConditionValidator::validate($target, ['deadline' => $source]);
        $this->assertTrue(true);
    }

    public function test_less_than_allowed_on_time(): void
    {
        $source = Field::time('meeting');
        $target = Field::text('morning')->visibleWhen('meeting', '<', '12:00');
        ConditionValidator::validate($target, ['meeting' => $source]);
        $this->assertTrue(true);
    }

    public function test_less_than_allowed_on_datetime(): void
    {
        $source = Field::datetime('published_at');
        $target = Field::text('draft')->visibleWhen('published_at', '>', '2026-01-01T00:00:00');
        ConditionValidator::validate($target, ['published_at' => $source]);
        $this->assertTrue(true);
    }

    public function test_less_than_not_allowed_on_text(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        $source = Field::text('name');
        $target = Field::text('other')->visibleWhen('name', '<', 'z');
        ConditionValidator::validate($target, ['name' => $source]);
    }

    public function test_greater_than_not_allowed_on_select(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        $source = Field::select('status')->options(['a' => 'A']);
        $target = Field::text('other')->visibleWhen('status', '>', 'a');
        ConditionValidator::validate($target, ['status' => $source]);
    }

    // -------------------------------------------------------------------------
    // contains / not_contains — text types only
    // -------------------------------------------------------------------------

    public function test_contains_allowed_on_text(): void
    {
        $source = Field::text('bio');
        $target = Field::text('note')->visibleWhen('bio', 'contains', 'developer');
        ConditionValidator::validate($target, ['bio' => $source]);
        $this->assertTrue(true);
    }

    public function test_contains_allowed_on_textarea(): void
    {
        $source = Field::textarea('description');
        $target = Field::text('note')->visibleWhen('description', 'not_contains', 'test');
        ConditionValidator::validate($target, ['description' => $source]);
        $this->assertTrue(true);
    }

    public function test_contains_not_allowed_on_number(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        $source = Field::number('count');
        $target = Field::text('note')->visibleWhen('count', 'contains', '5');
        ConditionValidator::validate($target, ['count' => $source]);
    }

    // -------------------------------------------------------------------------
    // empty / not_empty — all types
    // -------------------------------------------------------------------------

    public function test_empty_allowed_on_any_type(): void
    {
        foreach ([
            Field::text('f'),
            Field::number('f'),
            Field::select('f')->options(['a' => 'A']),
            Field::date('f'),
            Field::image('f'),
        ] as $source) {
            $target = Field::text('other')->visibleWhen($source->getKey(), 'empty', null);
            ConditionValidator::validate($target, [$source->getKey() => $source]);
        }
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // in / not_in — option types; value must be array
    // -------------------------------------------------------------------------

    public function test_in_allowed_on_select_with_array_value(): void
    {
        $source = Field::select('status')->options(['a' => 'A', 'b' => 'B']);
        $target = Field::text('note')->visibleWhenAll([['status', 'in', ['a', 'b']]]);
        ConditionValidator::validate($target, ['status' => $source]);
        $this->assertTrue(true);
    }

    public function test_in_allowed_on_checkbox(): void
    {
        $source = Field::checkbox('tags')->options(['php' => 'PHP', 'js' => 'JS']);
        $target = Field::text('note')->visibleWhenAll([['tags', 'not_in', ['js']]]);
        ConditionValidator::validate($target, ['tags' => $source]);
        $this->assertTrue(true);
    }

    public function test_in_not_allowed_on_text(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        $source = Field::text('name');
        $target = Field::text('note')->visibleWhenAll([['name', 'in', ['a', 'b']]]);
        ConditionValidator::validate($target, ['name' => $source]);
    }

    public function test_in_requires_array_value(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        $source = Field::select('status')->options(['a' => 'A']);
        $target = Field::text('note')->visibleWhenAll([['status', 'in', 'not-an-array']]);
        ConditionValidator::validate($target, ['status' => $source]);
    }

    // -------------------------------------------------------------------------
    // Unknown source field — skipped silently (cross-group is out of scope)
    // -------------------------------------------------------------------------

    public function test_unknown_source_field_skipped(): void
    {
        $target = Field::text('note')->visibleWhen('other_group_field', '==', 'value');
        ConditionValidator::validate($target, []); // empty map
        $this->assertTrue(true); // no exception
    }

    // -------------------------------------------------------------------------
    // No condition — skipped
    // -------------------------------------------------------------------------

    public function test_field_without_condition_passes(): void
    {
        $field = Field::text('plain');
        ConditionValidator::validate($field, []);
        $this->assertTrue(true);
    }
}
