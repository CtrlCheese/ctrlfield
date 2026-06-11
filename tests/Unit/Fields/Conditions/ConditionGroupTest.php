<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Conditions;

use FieldForge\Fields\Conditions\ConditionGroup;
use PHPUnit\Framework\TestCase;

class ConditionGroupTest extends TestCase
{
    public function test_all_group_type(): void
    {
        $cg = ConditionGroup::all([['field_a', '==', 'value']]);
        $this->assertSame('all', $cg->getType());
    }

    public function test_any_group_type(): void
    {
        $cg = ConditionGroup::any([['field_a', '==', 'value']]);
        $this->assertSame('any', $cg->getType());
    }

    public function test_to_array_format(): void
    {
        $cg  = ConditionGroup::all([
            ['project_type', '==', 'external'],
            ['client_tier', '!=', 'free'],
        ]);
        $arr = $cg->toArray();

        $this->assertSame('all', $arr['type']);
        $this->assertCount(2, $arr['conditions']);
        $this->assertSame('project_type', $arr['conditions'][0]['field']);
        $this->assertSame('==', $arr['conditions'][0]['operator']);
        $this->assertSame('external', $arr['conditions'][0]['value']);
        $this->assertSame('client_tier', $arr['conditions'][1]['field']);
        $this->assertSame('!=', $arr['conditions'][1]['operator']);
        $this->assertSame('free', $arr['conditions'][1]['value']);
    }

    public function test_to_array_is_json_serializable(): void
    {
        $cg  = ConditionGroup::any([['status', 'in', ['active', 'pending']]]);
        $arr = $cg->toArray();

        $json = json_encode($arr, JSON_THROW_ON_ERROR);
        $this->assertIsString($json);
    }

    public function test_get_conditions_returns_raw_tuples(): void
    {
        $raw = [['field_a', '==', 'val'], ['field_b', '!=', 'other']];
        $cg  = ConditionGroup::all($raw);
        $this->assertSame($raw, $cg->getConditions());
    }
}
