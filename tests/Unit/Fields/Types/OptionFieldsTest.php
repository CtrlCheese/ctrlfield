<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Field;
use FieldForge\Fields\Types\CheckboxField;
use FieldForge\Fields\Types\RadioField;
use FieldForge\Fields\Types\SelectField;
use PHPUnit\Framework\TestCase;

class OptionFieldsTest extends TestCase
{
    private const SAMPLE_OPTIONS = [
        'internal' => 'Internal Development',
        'external' => 'External Client',
    ];

    // --- SelectField ---

    public function test_select_stores_and_returns_options(): void
    {
        $field = Field::select('type')->options(self::SAMPLE_OPTIONS);

        $this->assertSame(self::SAMPLE_OPTIONS, $field->getOptions());
    }

    public function test_select_definition_includes_options(): void
    {
        $def = Field::select('type')->options(self::SAMPLE_OPTIONS)->getDefinition();

        $this->assertArrayHasKey('options', $def);
        $this->assertSame(self::SAMPLE_OPTIONS, $def['options']);
        $this->assertSame(FieldType::SELECT->value, $def['type']);
    }

    public function test_select_options_default_empty(): void
    {
        $this->assertSame([], Field::select('type')->getOptions());
    }

    // --- CheckboxField ---

    public function test_checkbox_stores_and_returns_options(): void
    {
        $field = Field::checkbox('features')->options(self::SAMPLE_OPTIONS);

        $this->assertSame(self::SAMPLE_OPTIONS, $field->getOptions());
    }

    public function test_checkbox_definition_includes_options(): void
    {
        $def = Field::checkbox('features')->options(self::SAMPLE_OPTIONS)->getDefinition();

        $this->assertArrayHasKey('options', $def);
        $this->assertSame(FieldType::CHECKBOX->value, $def['type']);
    }

    // --- RadioField ---

    public function test_radio_stores_and_returns_options(): void
    {
        $field = Field::radio('status')->options(self::SAMPLE_OPTIONS);

        $this->assertSame(self::SAMPLE_OPTIONS, $field->getOptions());
    }

    public function test_radio_definition_includes_options(): void
    {
        $def = Field::radio('status')->options(self::SAMPLE_OPTIONS)->getDefinition();

        $this->assertArrayHasKey('options', $def);
        $this->assertSame(FieldType::RADIO->value, $def['type']);
    }

    // --- Options fluent returns same instance ---

    public function test_options_returns_static(): void
    {
        $select   = Field::select('s');
        $checkbox = Field::checkbox('c');
        $radio    = Field::radio('r');

        $this->assertInstanceOf(SelectField::class, $select->options([]));
        $this->assertInstanceOf(CheckboxField::class, $checkbox->options([]));
        $this->assertInstanceOf(RadioField::class, $radio->options([]));
    }
}
