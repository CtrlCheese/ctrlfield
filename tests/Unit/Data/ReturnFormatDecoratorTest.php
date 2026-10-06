<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Data;

use CtrlField\Data\ReturnFormatDecorator;
use CtrlField\Fields\Field;
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

    // -------------------------------------------------------------------------
    // Gallery (Pro type — tested via anonymous FieldDefinition subclass)
    // -------------------------------------------------------------------------

    public function test_gallery_url_format_returns_array_of_strings(): void
    {
        $field = $this->makeField(\CtrlField\Enums\FieldType::GALLERY)->returnFormat('url');
        $result = ReturnFormatDecorator::apply([4, 8, 15], $field);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        foreach ($result as $url) {
            $this->assertIsString($url);
        }
    }

    public function test_gallery_url_format_with_non_array_returns_empty_array(): void
    {
        $field  = $this->makeField(\CtrlField\Enums\FieldType::GALLERY)->returnFormat('url');
        $result = ReturnFormatDecorator::apply('not-an-array', $field);

        $this->assertSame([], $result);
    }

    public function test_gallery_array_format_returns_array(): void
    {
        $field  = $this->makeField(\CtrlField\Enums\FieldType::GALLERY)->returnFormat('array');
        $result = ReturnFormatDecorator::apply([4, 8], $field);

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // POST_OBJECT / RELATIONSHIP — toObject with arrays (Pro types)
    // -------------------------------------------------------------------------

    public function test_object_format_on_post_object_single_id_returns_stdclass(): void
    {
        $field  = $this->makeField(\CtrlField\Enums\FieldType::POST_OBJECT)->returnFormat('object');
        $result = ReturnFormatDecorator::apply(42, $field);

        // stubs.php get_post() returns a stdClass with ID matching the int arg
        $this->assertIsObject($result);
        $this->assertSame(42, $result->ID);
    }

    public function test_object_format_on_post_object_array_of_ids_returns_array_of_objects(): void
    {
        $field  = $this->makeField(\CtrlField\Enums\FieldType::POST_OBJECT)->returnFormat('object');
        $result = ReturnFormatDecorator::apply([3, 7, 12], $field);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(3, $result[0]->ID);
        $this->assertSame(12, $result[2]->ID);
    }

    public function test_relationship_object_format_always_returns_array(): void
    {
        $field  = $this->makeField(\CtrlField\Enums\FieldType::RELATIONSHIP)->returnFormat('object');
        $result = ReturnFormatDecorator::apply([1, 2], $field);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // DATE → timestamp
    // -------------------------------------------------------------------------

    public function test_timestamp_format_returns_zero_for_empty_string(): void
    {
        $field  = Field::date('deadline')->returnFormat('timestamp');
        $result = ReturnFormatDecorator::apply('', $field);

        $this->assertSame(0, $result);
    }

    public function test_timestamp_format_parses_datetime_string(): void
    {
        $field  = Field::datetime('scheduled_at')->returnFormat('timestamp');
        $result = ReturnFormatDecorator::apply('2026-01-01 12:00:00', $field);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // -------------------------------------------------------------------------
    // Default passthrough
    // -------------------------------------------------------------------------

    public function test_unknown_format_returns_value_unchanged(): void
    {
        $field  = Field::text('title')->returnFormat('custom_format');
        $result = ReturnFormatDecorator::apply('hello', $field);

        $this->assertSame('hello', $result);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeField(\CtrlField\Enums\FieldType $type): \CtrlField\Fields\FieldDefinition
    {
        return new class($type, 'test') extends \CtrlField\Fields\FieldDefinition {
            public function __construct(
                private readonly \CtrlField\Enums\FieldType $fieldType,
                string $key,
            ) {
                parent::__construct($key);
            }

            public function getType(): \CtrlField\Enums\FieldType
            {
                return $this->fieldType;
            }
        };
    }
}
