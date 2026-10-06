<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class GroupFieldTest extends TestCase
{
    public function test_group_stores_fields(): void
    {
        $group = Field::object('address')->fields([
            Field::text('street')->label('Street'),
            Field::text('city')->label('City'),
        ]);

        $this->assertCount(2, $group->getFields());
    }

    public function test_group_definition_includes_nested_fields(): void
    {
        $def = Field::object('address')->fields([
            Field::text('street')->label('Street'),
        ])->getDefinition();

        $this->assertSame(FieldType::GROUP->value, $def['type']);
        $this->assertArrayHasKey('fields', $def);
        $this->assertCount(1, $def['fields']);
        $this->assertSame('street', $def['fields'][0]['key']);
        $this->assertSame('Street', $def['fields'][0]['label']);
    }

    public function test_group_can_contain_repeater(): void
    {
        $group = Field::object('project')->fields([
            Field::text('name'),
            Field::repeater('phases')->fields([
                Field::text('phase_name'),
            ]),
        ]);

        $def = $group->getDefinition();

        $this->assertCount(2, $def['fields']);
        $this->assertSame(FieldType::REPEATER->value, $def['fields'][1]['type']);
    }

    public function test_group_definition_is_json_serializable(): void
    {
        $group = Field::object('address')->label('Address')->fields([
            Field::text('street'),
            Field::text('city'),
        ]);

        $json = json_encode($group->getDefinition(), JSON_THROW_ON_ERROR);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('address', $decoded['key']);
        $this->assertCount(2, $decoded['fields']);
    }

    public function test_empty_group_is_valid(): void
    {
        $group = Field::object('empty')->fields([]);

        $this->assertEmpty($group->getDefinition()['fields']);
    }

    public function test_group_type_is_correct(): void
    {
        $this->assertSame(FieldType::GROUP, Field::object('g')->getType());
    }
}
