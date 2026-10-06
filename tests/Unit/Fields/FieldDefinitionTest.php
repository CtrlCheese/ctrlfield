<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields;

use CtrlField\Enums\AdminTab;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase
{
    public function test_key_is_returned_correctly(): void
    {
        $field = Field::text('client_name');

        $this->assertSame('client_name', $field->getKey());
    }

    public function test_label_fluent_method(): void
    {
        $field = Field::text('name')->label('Full Name');

        $this->assertSame('Full Name', $field->getDefinition()['label']);
    }

    public function test_required_defaults_to_false(): void
    {
        $this->assertFalse(Field::text('name')->getDefinition()['required']);
    }

    public function test_required_fluent_method(): void
    {
        $field = Field::text('name')->required();

        $this->assertTrue($field->getDefinition()['required']);
    }

    public function test_index_defaults_to_false(): void
    {
        $this->assertFalse(Field::text('name')->getDefinition()['index']);
    }

    public function test_set_index_fluent_method(): void
    {
        $field = Field::text('name')->setIndex(true);

        $this->assertTrue($field->getDefinition()['index']);
    }

    public function test_rest_exposed_defaults_to_false(): void
    {
        $this->assertFalse(Field::text('name')->getDefinition()['rest_exposed']);
    }

    public function test_show_in_rest_fluent_method(): void
    {
        $field = Field::text('name')->showInRest(true);

        $this->assertTrue($field->getDefinition()['rest_exposed']);
    }

    public function test_tab_defaults_to_content(): void
    {
        $this->assertSame(AdminTab::CONTENT->value, Field::text('name')->getDefinition()['tab']);
    }

    public function test_tab_fluent_method(): void
    {
        $field = Field::text('name')->tab(AdminTab::SIDEBAR);

        $this->assertSame(AdminTab::SIDEBAR->value, $field->getDefinition()['tab']);
    }

    public function test_condition_defaults_to_null(): void
    {
        $this->assertNull(Field::text('name')->getDefinition()['condition']);
        $this->assertNull(Field::text('name')->getConditionGroup());
    }

    public function test_visible_when_v1_api_produces_all_group(): void
    {
        $field = Field::text('client_name')
            ->visibleWhen('project_type', '==', 'external');

        $condition = $field->getDefinition()['condition'];

        $this->assertIsArray($condition);
        $this->assertSame('all', $condition['type']);
        $this->assertCount(1, $condition['conditions']);
        $this->assertSame('project_type', $condition['conditions'][0]['field']);
        $this->assertSame('==', $condition['conditions'][0]['operator']);
        $this->assertSame('external', $condition['conditions'][0]['value']);
    }

    public function test_visible_when_accepts_non_string_value(): void
    {
        $field = Field::number('count')->visibleWhen('enabled', '==', 1);

        $this->assertSame(1, $field->getDefinition()['condition']['conditions'][0]['value']);
    }

    public function test_fluent_methods_return_same_instance(): void
    {
        $field = Field::text('name');

        $this->assertSame($field, $field->label('X'));
        $this->assertSame($field, $field->required());
        $this->assertSame($field, $field->setIndex());
        $this->assertSame($field, $field->showInRest());
        $this->assertSame($field, $field->tab(AdminTab::CONTENT));
        $this->assertSame($field, $field->visibleWhen('f', '==', 'v'));
    }

    public function test_get_definition_contains_all_base_keys(): void
    {
        $def = Field::text('my_field')->getDefinition();

        $this->assertArrayHasKey('key', $def);
        $this->assertArrayHasKey('type', $def);
        $this->assertArrayHasKey('label', $def);
        $this->assertArrayHasKey('required', $def);
        $this->assertArrayHasKey('index', $def);
        $this->assertArrayHasKey('rest_exposed', $def);
        $this->assertArrayHasKey('tab', $def);
        $this->assertArrayHasKey('condition', $def);
    }

    public function test_get_definition_is_json_serializable(): void
    {
        $def = Field::text('name')
            ->label('Name')
            ->required()
            ->setIndex()
            ->visibleWhen('type', '==', 'external')
            ->getDefinition();

        $json = json_encode($def, JSON_THROW_ON_ERROR);

        $this->assertIsString($json);
        $this->assertNotEmpty($json);
    }

    public function test_full_chain_returns_correct_type_instance(): void
    {
        $field = Field::text('name')->label('Name')->setIndex(true);

        $this->assertInstanceOf(\CtrlField\Fields\Types\TextField::class, $field);
        $this->assertSame(FieldType::TEXT->value, $field->getDefinition()['type']);
    }
}
