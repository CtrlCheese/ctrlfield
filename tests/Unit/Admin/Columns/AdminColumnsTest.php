<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Admin\Columns;

use FieldForge\Fields\Exceptions\AdminColumnWithoutIndexException;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class AdminColumnsTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    // -------------------------------------------------------------------------
    // Fluent API
    // -------------------------------------------------------------------------

    public function test_admin_column_default_false(): void
    {
        $field = Field::text('name');
        $this->assertFalse($field->isAdminColumn());
        $this->assertFalse($field->isAdminColumnSortable());
    }

    public function test_admin_column_fluent_methods(): void
    {
        $field = Field::text('client')
            ->setIndex(true)
            ->adminColumn(true)
            ->adminColumnLabel('Client Name')
            ->adminColumnSortable(true);

        $this->assertTrue($field->isAdminColumn());
        $this->assertSame('Client Name', $field->getAdminColumnLabel());
        $this->assertTrue($field->isAdminColumnSortable());
    }

    public function test_admin_column_label_defaults_to_field_label(): void
    {
        $field = Field::text('client')->label('Client Name')->setIndex(true)->adminColumn(true);

        $this->assertSame('Client Name', $field->getAdminColumnLabel());
    }

    public function test_admin_column_in_definition_array(): void
    {
        $field = Field::text('client')->setIndex(true)->adminColumn(true);
        $def   = $field->getDefinition();

        $this->assertTrue($def['admin_column']);
        $this->assertFalse($def['admin_column_sortable']);
    }

    // -------------------------------------------------------------------------
    // isNumericSort
    // -------------------------------------------------------------------------

    public function test_numeric_sort_for_number(): void
    {
        $this->assertTrue(Field::number('n')->isNumericSort());
    }

    public function test_numeric_sort_for_range(): void
    {
        $this->assertTrue(Field::range('r')->isNumericSort());
    }

    public function test_not_numeric_sort_for_text(): void
    {
        $this->assertFalse(Field::text('t')->isNumericSort());
    }

    public function test_not_numeric_sort_for_date(): void
    {
        // ISO 8601 dates sort correctly as strings
        $this->assertFalse(Field::date('d')->isNumericSort());
    }

    // -------------------------------------------------------------------------
    // Register-time validation — adminColumn requires setIndex
    // -------------------------------------------------------------------------

    public function test_admin_column_without_index_throws_at_register(): void
    {
        $this->expectException(AdminColumnWithoutIndexException::class);
        $this->expectExceptionMessage("adminColumn(true)");

        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('note')->adminColumn(true),  // no setIndex — throws
            ])
            ->register();
    }

    public function test_admin_column_with_index_registers_cleanly(): void
    {
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('client')->setIndex(true)->adminColumn(true),
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('g'));
    }

    public function test_admin_column_false_without_index_registers(): void
    {
        // adminColumn(false) is default — no exception even without index
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('note'),     // no index, no adminColumn
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('g'));
    }

    public function test_non_column_field_unaffected(): void
    {
        // Fields without adminColumn(true) don't trigger the validation
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::number('budget'),
                Field::textarea('bio'),
            ])
            ->register();

        $this->assertTrue(FieldRegistry::has('g'));
    }
}
