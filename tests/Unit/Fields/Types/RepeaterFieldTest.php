<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Types;

use FieldForge\Fields\Exceptions\InvalidNestingDepthException;
use FieldForge\Fields\Field;
use FieldForge\Fields\Types\RepeaterField;
use PHPUnit\Framework\TestCase;

class RepeaterFieldTest extends TestCase
{
    public function test_depth_1_is_valid(): void
    {
        $repeater = Field::repeater('phases')->fields([
            Field::text('name'),
            Field::text('date'),
        ]);

        $this->assertCount(2, $repeater->getFields());
    }

    public function test_depth_2_is_valid(): void
    {
        $repeater = Field::repeater('outer')->fields([
            Field::repeater('inner')->fields([
                Field::text('name'),
            ]),
        ]);

        $this->assertInstanceOf(RepeaterField::class, $repeater->getFields()[0]);
    }

    public function test_depth_3_is_valid_maximum(): void
    {
        $repeater = Field::repeater('l1')->fields([
            Field::repeater('l2')->fields([
                Field::repeater('l3')->fields([
                    Field::text('leaf'),
                ]),
            ]),
        ]);

        $this->assertInstanceOf(RepeaterField::class, $repeater);
    }

    public function test_depth_4_throws_invalid_nesting_depth_exception(): void
    {
        $this->expectException(InvalidNestingDepthException::class);
        $this->expectExceptionMessage('maximum allowed depth of 3');

        Field::repeater('l1')->fields([
            Field::repeater('l2')->fields([
                Field::repeater('l3')->fields([
                    Field::repeater('l4'),
                ]),
            ]),
        ]);
    }

    public function test_depth_4_in_second_branch_also_throws(): void
    {
        $this->expectException(InvalidNestingDepthException::class);

        Field::repeater('l1')->fields([
            Field::text('ok'),
            Field::repeater('l2')->fields([
                Field::repeater('l3')->fields([
                    Field::repeater('l4'), // violation in second branch
                ]),
            ]),
        ]);
    }

    public function test_empty_fields_is_valid(): void
    {
        $repeater = Field::repeater('empty')->fields([]);

        $this->assertEmpty($repeater->getFields());
    }

    public function test_get_definition_includes_nested_fields(): void
    {
        $repeater = Field::repeater('schedule')->fields([
            Field::text('phase_name')->label('Phase Name'),
            Field::text('due_date')->label('Due Date'),
        ]);

        $def = $repeater->getDefinition();

        $this->assertArrayHasKey('fields', $def);
        $this->assertCount(2, $def['fields']);
        $this->assertSame('phase_name', $def['fields'][0]['key']);
        $this->assertSame('due_date', $def['fields'][1]['key']);
    }

    public function test_get_definition_is_json_serializable_with_nesting(): void
    {
        $repeater = Field::repeater('l1')->fields([
            Field::repeater('l2')->fields([
                Field::text('leaf'),
            ]),
        ]);

        $json = json_encode($repeater->getDefinition(), JSON_THROW_ON_ERROR);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('l1', $decoded['key']);
        $this->assertSame('l2', $decoded['fields'][0]['key']);
        $this->assertSame('leaf', $decoded['fields'][0]['fields'][0]['key']);
    }

    public function test_max_depth_constant_is_3(): void
    {
        $this->assertSame(3, RepeaterField::MAX_DEPTH);
    }
}
