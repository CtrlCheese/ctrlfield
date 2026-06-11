<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\Exceptions\InvalidWidthException;
use FieldForge\Fields\Field;
use FieldForge\Fields\Types\ColorField;
use FieldForge\Fields\Types\DateField;
use FieldForge\Fields\Types\DateTimeField;
use FieldForge\Fields\Types\LinkField;
use FieldForge\Fields\Types\OembedField;
use FieldForge\Fields\Types\RangeField;
use FieldForge\Fields\Types\TimeField;
use PHPUnit\Framework\TestCase;

class NewFieldTypesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Factory + type identity
    // -------------------------------------------------------------------------

    public function test_factory_returns_correct_instances(): void
    {
        $this->assertInstanceOf(DateField::class,     Field::date('d'));
        $this->assertInstanceOf(TimeField::class,     Field::time('t'));
        $this->assertInstanceOf(DateTimeField::class, Field::datetime('dt'));
        $this->assertInstanceOf(ColorField::class,    Field::color('c'));
        $this->assertInstanceOf(LinkField::class,     Field::link('l'));
        $this->assertInstanceOf(RangeField::class,    Field::range('r'));
        $this->assertInstanceOf(OembedField::class,   Field::oembed('o'));
    }

    public function test_field_types(): void
    {
        $this->assertSame(FieldType::DATE,     Field::date('d')->getType());
        $this->assertSame(FieldType::TIME,     Field::time('t')->getType());
        $this->assertSame(FieldType::DATETIME, Field::datetime('dt')->getType());
        $this->assertSame(FieldType::COLOR,    Field::color('c')->getType());
        $this->assertSame(FieldType::LINK,     Field::link('l')->getType());
        $this->assertSame(FieldType::RANGE,    Field::range('r')->getType());
        $this->assertSame(FieldType::OEMBED,   Field::oembed('o')->getType());
    }

    // -------------------------------------------------------------------------
    // DateField
    // -------------------------------------------------------------------------

    public function test_date_field_definition(): void
    {
        $field = Field::date('deadline')
            ->label('Deadline')
            ->format('d/m/Y')
            ->minDate('today')
            ->maxDate('2027-12-31');

        $def = $field->getDefinition();

        $this->assertSame('deadline', $def['key']);
        $this->assertSame('date', $def['type']);
        $this->assertSame('d/m/Y', $def['format']);
        $this->assertSame('today', $def['min_date']);
        $this->assertSame('2027-12-31', $def['max_date']);
    }

    public function test_date_field_defaults(): void
    {
        $def = Field::date('d')->getDefinition();

        $this->assertSame('Y-m-d', $def['format']);
        $this->assertNull($def['min_date']);
        $this->assertNull($def['max_date']);
    }

    // -------------------------------------------------------------------------
    // TimeField
    // -------------------------------------------------------------------------

    public function test_time_field_step(): void
    {
        $field = Field::time('meeting_time')->step(15);
        $def   = $field->getDefinition();

        $this->assertSame(15, $def['step']);
        $this->assertSame(15, $field->getStep());
    }

    public function test_time_field_step_defaults_to_1(): void
    {
        $this->assertSame(1, Field::time('t')->getStep());
    }

    // -------------------------------------------------------------------------
    // DateTimeField
    // -------------------------------------------------------------------------

    public function test_datetime_field_definition(): void
    {
        $field = Field::datetime('scheduled_at')
            ->format('d/m/Y H:i')
            ->minDate('2026-01-01T00:00:00');

        $def = $field->getDefinition();

        $this->assertSame('d/m/Y H:i', $def['format']);
        $this->assertSame('2026-01-01T00:00:00', $def['min_date']);
    }

    // -------------------------------------------------------------------------
    // ColorField
    // -------------------------------------------------------------------------

    public function test_color_field_palette(): void
    {
        $palette = ['#FF5733', '#33FF57', '#3357FF'];
        $field   = Field::color('brand_color')->palette($palette)->enableAlpha(true);
        $def     = $field->getDefinition();

        $this->assertSame($palette, $def['palette']);
        $this->assertTrue($def['enable_alpha']);
    }

    public function test_color_field_defaults(): void
    {
        $def = Field::color('c')->getDefinition();
        $this->assertSame([], $def['palette']);
        $this->assertFalse($def['enable_alpha']);
    }

    // -------------------------------------------------------------------------
    // LinkField
    // -------------------------------------------------------------------------

    public function test_link_field_show_target_default(): void
    {
        $this->assertTrue(Field::link('cta')->getShowTarget());
    }

    public function test_link_field_hide_target(): void
    {
        $field = Field::link('cta')->showTarget(false);
        $this->assertFalse($field->getShowTarget());
        $this->assertFalse($field->getDefinition()['show_target']);
    }

    // -------------------------------------------------------------------------
    // RangeField
    // -------------------------------------------------------------------------

    public function test_range_field_fluent_methods(): void
    {
        $field = Field::range('score')
            ->min(1.0)
            ->max(10.0)
            ->step(0.5)
            ->showValue(true);

        $def = $field->getDefinition();

        $this->assertSame(1.0, $def['min']);
        $this->assertSame(10.0, $def['max']);
        $this->assertSame(0.5, $def['step']);
        $this->assertTrue($def['show_value']);
    }

    public function test_range_field_defaults(): void
    {
        $def = Field::range('r')->getDefinition();

        $this->assertSame(0.0, $def['min']);
        $this->assertSame(100.0, $def['max']);
        $this->assertSame(1.0, $def['step']);
        $this->assertFalse($def['show_value']);
    }

    // -------------------------------------------------------------------------
    // OembedField
    // -------------------------------------------------------------------------

    public function test_oembed_field_preview_dimensions(): void
    {
        $field = Field::oembed('video')->previewWidth(800)->previewHeight(450);
        $def   = $field->getDefinition();

        $this->assertSame(800, $def['preview_width']);
        $this->assertSame(450, $def['preview_height']);
    }

    // -------------------------------------------------------------------------
    // Utility fluent methods on FieldDefinition
    // -------------------------------------------------------------------------

    public function test_placeholder_appears_in_definition(): void
    {
        $def = Field::text('name')->placeholder('Enter full name')->getDefinition();
        $this->assertSame('Enter full name', $def['placeholder']);
    }

    public function test_instructions_appears_in_definition(): void
    {
        $def = Field::text('bio')->instructions('<em>Max 200 words</em>')->getDefinition();
        $this->assertSame('<em>Max 200 words</em>', $def['instructions']);
    }

    public function test_read_only_flag(): void
    {
        $field = Field::text('slug')->readOnly();
        $this->assertTrue($field->isReadOnly());
        $this->assertTrue($field->getDefinition()['read_only']);
    }

    public function test_width_valid_values(): void
    {
        foreach ([25, 50, 75, 100] as $w) {
            $field = Field::text('f')->width($w);
            $this->assertSame($w, $field->getWidth());
        }
    }

    public function test_width_invalid_throws(): void
    {
        $this->expectException(InvalidWidthException::class);
        Field::text('f')->width(33);
    }

    public function test_default_value_not_set_by_default(): void
    {
        $field = Field::text('name');
        $this->assertFalse($field->hasDefault());
        $this->assertNull($field->getDefault());
        $this->assertNull($field->getDefinition()['default']);
    }

    public function test_default_value_is_stored(): void
    {
        $field = Field::text('name')->default('Anonymous');
        $this->assertTrue($field->hasDefault());
        $this->assertSame('Anonymous', $field->getDefault());
        $this->assertSame('Anonymous', $field->getDefinition()['default']);
    }

    public function test_default_null_is_valid(): void
    {
        $field = Field::select('status')->default(null);
        $this->assertTrue($field->hasDefault());
        $this->assertNull($field->getDefault());
    }
}
