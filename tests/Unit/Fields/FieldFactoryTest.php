<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields;

use FieldForge\Builder\FieldGroup;
use FieldForge\Enums\FieldType;
use FieldForge\Fields\Field;
use FieldForge\Fields\Types\CheckboxField;
use FieldForge\Fields\Types\EmailField;
use FieldForge\Fields\Types\FileField;
use FieldForge\Fields\Types\GroupField;
use FieldForge\Fields\Types\ImageField;
use FieldForge\Fields\Types\NumberField;
use FieldForge\Fields\Types\RadioField;
use FieldForge\Fields\Types\RepeaterField;
use FieldForge\Fields\Types\SelectField;
use FieldForge\Fields\Types\TextareaField;
use FieldForge\Fields\Types\TextField;
use FieldForge\Fields\Types\UrlField;
use FieldForge\Fields\Types\WysiwygField;
use PHPUnit\Framework\TestCase;

class FieldFactoryTest extends TestCase
{
    /** @return array<string, array{0: string, 1: class-string, 2: FieldType}> */
    public static function fieldTypeProvider(): array
    {
        return [
            'text'     => ['text',     TextField::class,     FieldType::TEXT],
            'textarea' => ['textarea', TextareaField::class, FieldType::TEXTAREA],
            'number'   => ['number',   NumberField::class,   FieldType::NUMBER],
            'email'    => ['email',    EmailField::class,    FieldType::EMAIL],
            'url'      => ['url',      UrlField::class,      FieldType::URL],
            'select'   => ['select',   SelectField::class,   FieldType::SELECT],
            'checkbox' => ['checkbox', CheckboxField::class, FieldType::CHECKBOX],
            'radio'    => ['radio',    RadioField::class,    FieldType::RADIO],
            'image'    => ['image',    ImageField::class,    FieldType::IMAGE],
            'file'     => ['file',     FileField::class,     FieldType::FILE],
            'wysiwyg'  => ['wysiwyg',  WysiwygField::class,  FieldType::WYSIWYG],
            'object'   => ['object',   GroupField::class,    FieldType::GROUP],
            'repeater' => ['repeater', RepeaterField::class, FieldType::REPEATER],
        ];
    }

    /**
     * @param class-string $expectedClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fieldTypeProvider')]
    public function test_factory_returns_correct_instance(
        string $factoryMethod,
        string $expectedClass,
        FieldType $expectedType
    ): void {
        $field = Field::{$factoryMethod}('my_key');

        $this->assertInstanceOf($expectedClass, $field);
        $this->assertSame('my_key', $field->getKey());
        $this->assertSame($expectedType, $field->getType());
        $this->assertSame($expectedType->value, $field->getDefinition()['type']);
    }

    public function test_group_returns_field_group_builder(): void
    {
        $group = Field::group('my_group');

        $this->assertInstanceOf(FieldGroup::class, $group);
        $this->assertSame('my_group', $group->getKey());
    }

    public function test_factory_each_call_returns_new_instance(): void
    {
        $a = Field::text('name');
        $b = Field::text('name');

        $this->assertNotSame($a, $b);
    }
}
