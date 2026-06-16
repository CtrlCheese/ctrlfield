<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Field;
use FieldForge\Fields\Types\AccordionField;
use FieldForge\Fields\Types\ButtonGroupField;
use FieldForge\Fields\Types\CodeField;
use FieldForge\Fields\Types\IconField;
use FieldForge\Fields\Types\MessageField;
use FieldForge\Fields\Types\SeparatorField;
use FieldForge\Fields\Types\TabField;
use FieldForge\Fields\Types\TrueFalseField;
use FieldForge\Fields\Types\UserField;
use PHPUnit\Framework\TestCase;

class Cycles4FieldTypesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // TrueFalseField
    // -------------------------------------------------------------------------

    public function test_true_false_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(TrueFalseField::class, Field::trueFalse('active'));
    }

    public function test_true_false_type(): void
    {
        $this->assertSame(FieldType::TRUE_FALSE, Field::trueFalse('active')->getType());
    }

    public function test_true_false_is_not_ui_only(): void
    {
        $this->assertFalse(Field::trueFalse('active')->isUiOnly());
    }

    public function test_true_false_default_labels(): void
    {
        $field = Field::trueFalse('active');

        $this->assertSame('Yes', $field->getTrueLabel());
        $this->assertSame('No', $field->getFalseLabel());
        $this->assertSame('', $field->getMessage());
    }

    public function test_true_false_fluent_labels(): void
    {
        $field = Field::trueFalse('maintenance')
            ->trueLabel('On')
            ->falseLabel('Off')
            ->message('Enable maintenance mode');

        $this->assertSame('On', $field->getTrueLabel());
        $this->assertSame('Off', $field->getFalseLabel());
        $this->assertSame('Enable maintenance mode', $field->getMessage());
    }

    public function test_true_false_definition_contains_labels_and_message(): void
    {
        $def = Field::trueFalse('active')
            ->trueLabel('Enabled')
            ->falseLabel('Disabled')
            ->message('Toggle the feature')
            ->getDefinition();

        $this->assertSame('Enabled', $def['true_label']);
        $this->assertSame('Disabled', $def['false_label']);
        $this->assertSame('Toggle the feature', $def['message']);
        $this->assertSame('true_false', $def['type']);
    }

    // -------------------------------------------------------------------------
    // ButtonGroupField
    // -------------------------------------------------------------------------

    public function test_button_group_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(ButtonGroupField::class, Field::buttonGroup('size'));
    }

    public function test_button_group_type(): void
    {
        $this->assertSame(FieldType::BUTTON_GROUP, Field::buttonGroup('size')->getType());
    }

    public function test_button_group_options_fluent(): void
    {
        $opts  = ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'];
        $field = Field::buttonGroup('size')->options($opts);

        $this->assertSame($opts, $field->getOptions());
    }

    public function test_button_group_allow_null_defaults_false(): void
    {
        $this->assertFalse(Field::buttonGroup('size')->isAllowNull());
    }

    public function test_button_group_allow_null_fluent(): void
    {
        $field = Field::buttonGroup('size')->allowNull();
        $this->assertTrue($field->isAllowNull());
    }

    public function test_button_group_definition(): void
    {
        $opts = ['a' => 'A', 'b' => 'B'];
        $def  = Field::buttonGroup('choice')->options($opts)->allowNull()->getDefinition();

        $this->assertSame($opts, $def['options']);
        $this->assertTrue($def['allow_null']);
        $this->assertSame('button_group', $def['type']);
    }

    // -------------------------------------------------------------------------
    // UserField
    // -------------------------------------------------------------------------

    public function test_user_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(UserField::class, Field::user('author'));
    }

    public function test_user_type(): void
    {
        $this->assertSame(FieldType::USER, Field::user('author')->getType());
    }

    public function test_user_single_by_default(): void
    {
        $this->assertFalse(Field::user('author')->isMultiple());
    }

    public function test_user_multiple_fluent(): void
    {
        $field = Field::user('contributors')->multiple();
        $this->assertTrue($field->isMultiple());
    }

    public function test_user_roles_fluent(): void
    {
        $field = Field::user('editor')->roles(['editor', 'author']);
        $this->assertSame(['editor', 'author'], $field->getRoles());
    }

    public function test_user_definition_contains_all_keys(): void
    {
        $def = Field::user('assigned_to')
            ->multiple()
            ->roles(['subscriber'])
            ->minUsers(1)
            ->maxUsers(5)
            ->getDefinition();

        $this->assertTrue($def['multiple']);
        $this->assertSame(['subscriber'], $def['roles']);
        $this->assertSame(1, $def['min_users']);
        $this->assertSame(5, $def['max_users']);
    }

    // -------------------------------------------------------------------------
    // IconField
    // -------------------------------------------------------------------------

    public function test_icon_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(IconField::class, Field::icon('social_icon'));
    }

    public function test_icon_type(): void
    {
        $this->assertSame(FieldType::ICON, Field::icon('social_icon')->getType());
    }

    public function test_icon_default_library_is_dashicons(): void
    {
        $this->assertSame('dashicons', Field::icon('ico')->getLibrary());
    }

    public function test_icon_library_fluent(): void
    {
        $field = Field::icon('ico')->library('fontawesome');
        $this->assertSame('fontawesome', $field->getLibrary());
    }

    public function test_icon_definition(): void
    {
        $def = Field::icon('ico')->library('fontawesome')->getDefinition();
        $this->assertSame('fontawesome', $def['library']);
        $this->assertSame('icon', $def['type']);
    }

    // -------------------------------------------------------------------------
    // CodeField
    // -------------------------------------------------------------------------

    public function test_code_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(CodeField::class, Field::code('snippet'));
    }

    public function test_code_type(): void
    {
        $this->assertSame(FieldType::CODE, Field::code('snippet')->getType());
    }

    public function test_code_defaults(): void
    {
        $field = Field::code('snippet');
        $this->assertSame('text', $field->getLanguage());
        $this->assertSame(10, $field->getRows());
        $this->assertFalse($field->isWrapLines());
    }

    public function test_code_fluent_methods(): void
    {
        $field = Field::code('snippet')
            ->language('php')
            ->rows(20)
            ->wrapLines();

        $this->assertSame('php', $field->getLanguage());
        $this->assertSame(20, $field->getRows());
        $this->assertTrue($field->isWrapLines());
    }

    public function test_code_definition(): void
    {
        $def = Field::code('snippet')->language('javascript')->rows(15)->getDefinition();
        $this->assertSame('javascript', $def['language']);
        $this->assertSame(15, $def['rows']);
        $this->assertSame('code', $def['type']);
    }

    // -------------------------------------------------------------------------
    // TabField (UI-only)
    // -------------------------------------------------------------------------

    public function test_tab_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(TabField::class, Field::tab('details'));
    }

    public function test_tab_type(): void
    {
        $this->assertSame(FieldType::TAB, Field::tab('details')->getType());
    }

    public function test_tab_is_ui_only(): void
    {
        $this->assertTrue(Field::tab('details')->isUiOnly());
    }

    // -------------------------------------------------------------------------
    // AccordionField (UI-only)
    // -------------------------------------------------------------------------

    public function test_accordion_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(AccordionField::class, Field::accordion('advanced'));
    }

    public function test_accordion_type(): void
    {
        $this->assertSame(FieldType::ACCORDION, Field::accordion('advanced')->getType());
    }

    public function test_accordion_is_ui_only(): void
    {
        $this->assertTrue(Field::accordion('advanced')->isUiOnly());
    }

    public function test_accordion_open_by_default(): void
    {
        $this->assertTrue(Field::accordion('advanced')->isOpen());
    }

    public function test_accordion_closed_fluent(): void
    {
        $field = Field::accordion('advanced')->open(false);
        $this->assertFalse($field->isOpen());
        $this->assertFalse($field->getDefinition()['open']);
    }

    // -------------------------------------------------------------------------
    // MessageField (UI-only)
    // -------------------------------------------------------------------------

    public function test_message_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(MessageField::class, Field::message('notice'));
    }

    public function test_message_type(): void
    {
        $this->assertSame(FieldType::MESSAGE, Field::message('notice')->getType());
    }

    public function test_message_is_ui_only(): void
    {
        $this->assertTrue(Field::message('notice')->isUiOnly());
    }

    public function test_message_content_and_type_fluent(): void
    {
        $field = Field::message('warn')
            ->content('<strong>Warning:</strong> This cannot be undone.')
            ->type('warning');

        $this->assertSame('<strong>Warning:</strong> This cannot be undone.', $field->getContent());
        $this->assertSame('warning', $field->getMessageType());
    }

    public function test_message_defaults(): void
    {
        $field = Field::message('info');
        $this->assertSame('', $field->getContent());
        $this->assertSame('info', $field->getMessageType());
    }

    // -------------------------------------------------------------------------
    // SeparatorField (UI-only)
    // -------------------------------------------------------------------------

    public function test_separator_factory_returns_correct_instance(): void
    {
        $this->assertInstanceOf(SeparatorField::class, Field::separator('divider'));
    }

    public function test_separator_type(): void
    {
        $this->assertSame(FieldType::SEPARATOR, Field::separator('divider')->getType());
    }

    public function test_separator_is_ui_only(): void
    {
        $this->assertTrue(Field::separator('divider')->isUiOnly());
    }
}
