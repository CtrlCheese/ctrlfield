<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Stages\TypeCoercionStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class TypeCoercionStageTest extends TestCase
{
    private TypeCoercionStage $stage;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        $this->stage = new TypeCoercionStage();
    }

    private function contextWith(array $fields, FieldGroup $group): PipelineContext
    {
        $ctx         = new PipelineContext(1, [], [$group]);
        $ctx->fields = $fields;
        return $ctx;
    }

    public function test_coerces_number_string_to_int(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('score')]);
        $context = $this->contextWith(['score' => '42'], $group);

        $this->stage->handle($context);

        $this->assertSame(42, $context->fields['score']);
    }

    public function test_coerces_number_string_to_float(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('price')]);
        $context = $this->contextWith(['price' => '9.99'], $group);

        $this->stage->handle($context);

        $this->assertSame(9.99, $context->fields['price']);
    }

    public function test_coerces_non_numeric_number_to_zero(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('count')]);
        $context = $this->contextWith(['count' => 'abc'], $group);

        $this->stage->handle($context);

        $this->assertSame(0, $context->fields['count']);
    }

    public function test_coerces_image_to_int(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::image('photo')]);
        $context = $this->contextWith(['photo' => '123'], $group);

        $this->stage->handle($context);

        $this->assertSame(123, $context->fields['photo']);
    }

    public function test_coerces_invalid_image_to_zero(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::image('photo')]);
        $context = $this->contextWith(['photo' => 'not-a-number'], $group);

        $this->stage->handle($context);

        $this->assertSame(0, $context->fields['photo']);
    }

    public function test_coerces_checkbox_string_to_empty_array(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::checkbox('features')->options(['a' => 'A', 'b' => 'B']),
        ]);
        $context = $this->contextWith(['features' => 'not-an-array'], $group);

        $this->stage->handle($context);

        $this->assertSame([], $context->fields['features']);
    }

    public function test_coerces_checkbox_array_to_indexed_array(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::checkbox('features')->options(['a' => 'A', 'b' => 'B']),
        ]);
        $context = $this->contextWith(['features' => ['a', 'b']], $group);

        $this->stage->handle($context);

        $this->assertSame(['a', 'b'], $context->fields['features']);
    }

    public function test_coerces_repeater_to_indexed_array(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::repeater('phases')->fields([Field::text('name')]),
        ]);
        $context = $this->contextWith(['phases' => [1 => ['name' => 'A']]], $group);

        $this->stage->handle($context);

        // array_values should re-index
        $this->assertSame([['name' => 'A']], $context->fields['phases']);
    }

    public function test_text_fields_remain_strings(): void
    {
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::text('name')]);
        $context = $this->contextWith(['name' => 'Acme'], $group);

        $this->stage->handle($context);

        $this->assertIsString($context->fields['name']);
        $this->assertSame('Acme', $context->fields['name']);
    }

    public function test_unknown_field_falls_back_to_string_coercion(): void
    {
        // Field exists in payload but NOT in schema — TypeCoercion treats as TEXT
        $group   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::text('known')]);
        $context = new PipelineContext(1, [], [$group]);
        $context->fields = ['known' => 'hello', 'unknown' => 123];

        $this->stage->handle($context);

        // unknown not in schema → falls back to string cast
        $this->assertSame('123', $context->fields['unknown']);
    }
}
