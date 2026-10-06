<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Field;
use CtrlField\Fields\Types\ComputedField;
use PHPUnit\Framework\TestCase;

class ComputedFieldTest extends TestCase
{
    public function test_computed_field_has_computed_type(): void
    {
        $field = Field::computed('total', static fn($f) => 0);

        $this->assertSame(FieldType::COMPUTED, $field->getType());
    }

    public function test_compute_calls_callback(): void
    {
        $field = Field::computed('total', static fn($f) => ($f['qty'] ?? 0) * ($f['price'] ?? 0));

        $result = $field->compute(['qty' => 3, 'price' => 10]);

        $this->assertSame(30, $result);
    }

    public function test_show_in_admin_defaults_false(): void
    {
        $field = Field::computed('total', static fn($f) => 0);

        $this->assertFalse($field->isShowInAdmin());
    }

    public function test_show_in_admin_can_be_enabled(): void
    {
        $field = Field::computed('total', static fn($f) => 0)->showInAdmin();

        $this->assertTrue($field->isShowInAdmin());
    }

    public function test_computed_field_is_instance_of_computed_field(): void
    {
        $field = Field::computed('total', static fn($f) => 0);

        $this->assertInstanceOf(ComputedField::class, $field);
    }

    public function test_computed_field_has_correct_key(): void
    {
        $field = Field::computed('full_name', static fn($f) => '');

        $this->assertSame('full_name', $field->getKey());
    }
}
