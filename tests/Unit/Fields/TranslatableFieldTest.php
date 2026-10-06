<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields;

use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class TranslatableFieldTest extends TestCase
{
    public function test_field_is_translatable_by_default(): void
    {
        $field = Field::text('name');
        $this->assertTrue($field->isTranslatable());
    }

    public function test_translate_false_marks_field_as_shared(): void
    {
        $field = Field::text('price')->translate(false);
        $this->assertFalse($field->isTranslatable());
    }

    public function test_translate_true_explicitly_marks_field_as_translatable(): void
    {
        $field = Field::number('views')->translate(true);
        $this->assertTrue($field->isTranslatable());
    }

    public function test_translate_false_is_reflected_in_definition(): void
    {
        $def = Field::text('sku')->translate(false)->getDefinition();
        $this->assertFalse($def['translatable']);
    }

    public function test_translatable_true_is_in_definition_by_default(): void
    {
        $def = Field::text('title')->getDefinition();
        $this->assertTrue($def['translatable']);
    }

    public function test_translate_returns_same_instance_for_chaining(): void
    {
        $field = Field::text('x');
        $this->assertSame($field, $field->translate(false));
    }

    public function test_various_field_types_support_translate(): void
    {
        $this->assertFalse(Field::number('price')->translate(false)->isTranslatable());
        $this->assertFalse(Field::image('logo')->translate(false)->isTranslatable());
        $this->assertFalse(Field::select('status')->translate(false)->isTranslatable());
        $this->assertFalse(Field::date('published_at')->translate(false)->isTranslatable());
    }

    public function test_translate_true_is_noop_on_translatable_field(): void
    {
        $field = Field::text('body')->translate(true);
        $this->assertTrue($field->isTranslatable());
    }
}
