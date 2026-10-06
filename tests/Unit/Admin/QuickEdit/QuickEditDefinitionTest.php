<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\QuickEdit;

use CtrlField\Fields\Exceptions\BulkEditOnInvalidTypeException;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class QuickEditDefinitionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // quickEdit() fluent
    // -------------------------------------------------------------------------

    public function test_quick_edit_defaults_false(): void
    {
        $field = Field::text('name');

        $this->assertFalse($field->isQuickEdit());
        $this->assertFalse($field->getDefinition()['quick_edit']);
    }

    public function test_quick_edit_enabled(): void
    {
        $field = Field::text('client_name')->quickEdit();

        $this->assertTrue($field->isQuickEdit());
        $this->assertTrue($field->getDefinition()['quick_edit']);
    }

    public function test_quick_edit_label_defaults_to_field_label(): void
    {
        $field = Field::text('client_name')->label('Client Name')->quickEdit();

        $this->assertSame('Client Name', $field->getQuickEditLabel());
    }

    public function test_quick_edit_label_override(): void
    {
        $field = Field::text('client_name')
            ->label('Client Name')
            ->quickEdit()
            ->quickEditLabel('Client');

        $this->assertSame('Client', $field->getQuickEditLabel());
        $this->assertSame('Client', $field->getDefinition()['quick_edit_label']);
    }

    public function test_quick_edit_without_admin_column_is_allowed(): void
    {
        $field = Field::text('status')->quickEdit();

        $this->assertTrue($field->isQuickEdit());
        $this->assertFalse($field->isAdminColumn());
    }

    // -------------------------------------------------------------------------
    // bulkEdit() fluent
    // -------------------------------------------------------------------------

    public function test_bulk_edit_defaults_false(): void
    {
        $field = Field::select('status')->options(['a' => 'A']);

        $this->assertFalse($field->isBulkEdit());
    }

    public function test_bulk_edit_on_select_passes(): void
    {
        $field = Field::select('status')
            ->options(['draft' => 'Draft', 'live' => 'Live'])
            ->bulkEdit();

        $this->assertTrue($field->isBulkEdit());
        $this->assertTrue($field->getDefinition()['bulk_edit']);
    }

    public function test_bulk_edit_on_radio_passes(): void
    {
        $field = Field::radio('visibility')
            ->options(['public' => 'Public', 'private' => 'Private'])
            ->bulkEdit();

        $this->assertTrue($field->isBulkEdit());
    }

    public function test_bulk_edit_on_checkbox_passes(): void
    {
        $field = Field::checkbox('tags')
            ->options(['a' => 'A', 'b' => 'B'])
            ->bulkEdit();

        $this->assertTrue($field->isBulkEdit());
    }

    public function test_bulk_edit_on_text_throws(): void
    {
        $this->expectException(BulkEditOnInvalidTypeException::class);

        Field::text('client_name')->bulkEdit();
    }

    public function test_bulk_edit_on_number_throws(): void
    {
        $this->expectException(BulkEditOnInvalidTypeException::class);

        Field::number('year')->bulkEdit();
    }

    public function test_bulk_edit_on_email_throws(): void
    {
        $this->expectException(BulkEditOnInvalidTypeException::class);

        Field::email('contact')->bulkEdit();
    }

    public function test_bulk_edit_label_override(): void
    {
        $field = Field::select('status')
            ->options(['a' => 'A'])
            ->label('Status')
            ->bulkEdit()
            ->bulkEditLabel('Change Status');

        $this->assertSame('Change Status', $field->getBulkEditLabel());
        $this->assertSame('Change Status', $field->getDefinition()['bulk_edit_label']);
    }

    public function test_bulk_edit_label_defaults_to_field_label(): void
    {
        $field = Field::select('status')
            ->options(['a' => 'A'])
            ->label('Status')
            ->bulkEdit();

        $this->assertSame('Status', $field->getBulkEditLabel());
    }

    // -------------------------------------------------------------------------
    // getDefinition() completeness
    // -------------------------------------------------------------------------

    public function test_definition_contains_all_quick_edit_keys(): void
    {
        $def = Field::text('name')->getDefinition();

        $this->assertArrayHasKey('quick_edit', $def);
        $this->assertArrayHasKey('quick_edit_label', $def);
        $this->assertArrayHasKey('bulk_edit', $def);
        $this->assertArrayHasKey('bulk_edit_label', $def);
    }
}
