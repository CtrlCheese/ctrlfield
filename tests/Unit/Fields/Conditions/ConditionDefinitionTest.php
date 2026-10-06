<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Conditions;

use CtrlField\Fields\Exceptions\DuplicateConditionException;
use CtrlField\Fields\Exceptions\InvalidOperatorForTypeException;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class ConditionDefinitionTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    // -------------------------------------------------------------------------
    // v1 backwards compatibility
    // -------------------------------------------------------------------------

    public function test_visible_when_v1_still_works(): void
    {
        $field = Field::text('client')->visibleWhen('project_type', '==', 'external');
        $cg    = $field->getConditionGroup();

        $this->assertNotNull($cg);
        $this->assertSame('all', $cg->getType());
        $this->assertCount(1, $cg->getConditions());
    }

    public function test_v1_condition_json_has_new_format(): void
    {
        $field = Field::text('client')->visibleWhen('project_type', '==', 'external');
        $def   = $field->getDefinition();

        $this->assertArrayHasKey('condition', $def);
        $this->assertArrayNotHasKey('visible_when', $def);
        $this->assertSame('all', $def['condition']['type']);
        $this->assertSame('project_type', $def['condition']['conditions'][0]['field']);
    }

    // -------------------------------------------------------------------------
    // visibleWhenAll
    // -------------------------------------------------------------------------

    public function test_visible_when_all_and_group(): void
    {
        $field = Field::text('feature')->visibleWhenAll([
            ['project_type', '==', 'external'],
            ['client_tier', '!=', 'free'],
        ]);
        $cg = $field->getConditionGroup();

        $this->assertSame('all', $cg->getType());
        $this->assertCount(2, $cg->getConditions());
    }

    // -------------------------------------------------------------------------
    // visibleWhenAny
    // -------------------------------------------------------------------------

    public function test_visible_when_any_or_group(): void
    {
        $field = Field::text('feature')->visibleWhenAny([
            ['project_type', '==', 'external'],
            ['project_type', '==', 'agency'],
        ]);
        $cg = $field->getConditionGroup();

        $this->assertSame('any', $cg->getType());
        $this->assertCount(2, $cg->getConditions());
    }

    // -------------------------------------------------------------------------
    // DuplicateConditionException
    // -------------------------------------------------------------------------

    public function test_second_visible_when_throws_duplicate(): void
    {
        $this->expectException(DuplicateConditionException::class);

        Field::text('f')
            ->visibleWhen('a', '==', 'x')
            ->visibleWhen('b', '==', 'y'); // second call throws
    }

    public function test_visible_when_all_then_any_throws_duplicate(): void
    {
        $this->expectException(DuplicateConditionException::class);

        Field::text('f')
            ->visibleWhenAll([['a', '==', 'x']])
            ->visibleWhenAny([['b', '!=', 'y']]);
    }

    public function test_visible_when_then_visible_when_all_throws_duplicate(): void
    {
        $this->expectException(DuplicateConditionException::class);

        Field::text('f')
            ->visibleWhen('a', '==', 'x')
            ->visibleWhenAll([['b', '==', 'y']]);
    }

    // -------------------------------------------------------------------------
    // Unknown operator at definition time
    // -------------------------------------------------------------------------

    public function test_unknown_operator_throws_at_definition_time(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);
        $this->expectExceptionMessageMatches('/Unknown operator/');

        Field::text('f')->visibleWhen('other', 'INVALID', 'x');
    }

    public function test_unknown_operator_in_all_group_throws(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        Field::text('f')->visibleWhenAll([['other', 'xyzzy', 'value']]);
    }

    // -------------------------------------------------------------------------
    // Operator-type mismatch at register() time
    // -------------------------------------------------------------------------

    public function test_less_than_on_text_throws_at_register(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('name'),
                Field::text('other')->visibleWhen('name', '<', 'z'),
            ])
            ->register();
    }

    public function test_contains_on_number_throws_at_register(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::number('score'),
                Field::text('other')->visibleWhen('score', 'contains', '5'),
            ])
            ->register();
    }

    public function test_in_with_non_array_value_throws_at_register(): void
    {
        $this->expectException(InvalidOperatorForTypeException::class);

        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::select('status')->options(['a' => 'A']),
                Field::text('other')->visibleWhenAll([['status', 'in', 'not-array']]),
            ])
            ->register();
    }

    // -------------------------------------------------------------------------
    // Valid complex examples register successfully
    // -------------------------------------------------------------------------

    public function test_valid_all_group_registers(): void
    {
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::select('project_type')->options(['ext' => 'External', 'int' => 'Internal']),
                Field::text('client_name')->visibleWhenAll([
                    ['project_type', '==', 'ext'],
                ]),
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('g'));
    }

    public function test_valid_any_group_registers(): void
    {
        Field::group('ga')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::select('type')->options(['a' => 'A', 'b' => 'B']),
                Field::text('note')->visibleWhenAny([
                    ['type', '==', 'a'],
                    ['type', '==', 'b'],
                ]),
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('ga'));
    }
}
