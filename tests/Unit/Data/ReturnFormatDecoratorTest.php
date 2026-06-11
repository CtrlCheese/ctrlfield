<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Data;

use FieldForge\Data\ReturnFormatDecorator;
use FieldForge\Fields\Field;
use PHPUnit\Framework\TestCase;

class ReturnFormatDecoratorTest extends TestCase
{
    public function test_apply_returns_value_unchanged_when_no_return_format(): void
    {
        $field = Field::text('name')->label('Name');

        $result = ReturnFormatDecorator::apply('hello', $field);

        $this->assertSame('hello', $result);
    }

    public function test_apply_returns_null_for_null_value(): void
    {
        $field = Field::image('logo')->returnFormat('url');

        $result = ReturnFormatDecorator::apply(null, $field);

        $this->assertNull($result);
    }

    public function test_apply_url_format_on_image_field_calls_attachment_url(): void
    {
        $field  = Field::image('logo')->returnFormat('url');
        $result = ReturnFormatDecorator::apply(42, $field);

        // wp_get_attachment_url is stubbed to return false → empty string
        $this->assertSame('', $result);
    }

    public function test_apply_url_format_on_text_field_casts_to_string(): void
    {
        $field  = Field::text('name')->returnFormat('url');
        $result = ReturnFormatDecorator::apply(123, $field);

        $this->assertSame('123', $result);
    }

    public function test_apply_array_format_on_image_field_returns_array(): void
    {
        $field  = Field::image('logo')->returnFormat('array');
        $result = ReturnFormatDecorator::apply(42, $field);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('alt', $result);
        $this->assertSame(42, $result['id']);
    }

    public function test_apply_array_format_on_file_field_returns_array(): void
    {
        $field  = Field::file('cv')->returnFormat('array');
        $result = ReturnFormatDecorator::apply(99, $field);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('mime', $result);
    }

    public function test_apply_datetime_format_parses_date_string(): void
    {
        $field  = Field::date('deadline')->returnFormat('DateTime');
        $result = ReturnFormatDecorator::apply('2026-06-10', $field);

        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-06-10', $result->format('Y-m-d'));
    }

    public function test_apply_datetime_format_returns_null_for_empty(): void
    {
        $field  = Field::date('deadline')->returnFormat('DateTime');
        $result = ReturnFormatDecorator::apply('', $field);

        $this->assertNull($result);
    }

    public function test_apply_timestamp_from_date_string(): void
    {
        $field  = Field::date('deadline')->returnFormat('timestamp');
        $result = ReturnFormatDecorator::apply('2026-01-01', $field);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_return_format_fluent_stores_value(): void
    {
        $field = Field::image('logo')->returnFormat('array');

        $this->assertSame('array', $field->getReturnFormat());
    }
}
