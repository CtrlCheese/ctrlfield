<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Stages\TypeCoercionStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Edge cases not covered in TypeCoercionStageTest:
 * null inputs, group/repeater recursion, bool inputs, float precision.
 */
class TypeCoercionNullEdgeCasesTest extends TestCase
{
    private TypeCoercionStage $stage;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        $this->stage = new TypeCoercionStage();
    }

    private function ctx(array $fields, FieldGroup $group): PipelineContext
    {
        $c         = new PipelineContext(1, [], [$group]);
        $c->fields = $fields;
        return $c;
    }

    // -------------------------------------------------------------------------
    // null → type defaults
    // -------------------------------------------------------------------------

    public function test_null_number_coerces_to_zero(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('qty')]);
        $ctx = $this->ctx(['qty' => null], $g);
        $this->stage->handle($ctx);
        $this->assertSame(0, $ctx->fields['qty']);
    }

    public function test_null_text_coerces_to_empty_string(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::text('name')]);
        $ctx = $this->ctx(['name' => null], $g);
        $this->stage->handle($ctx);
        $this->assertSame('', $ctx->fields['name']);
    }

    public function test_null_image_coerces_to_zero(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::image('photo')]);
        $ctx = $this->ctx(['photo' => null], $g);
        $this->stage->handle($ctx);
        $this->assertSame(0, $ctx->fields['photo']);
    }

    public function test_null_checkbox_coerces_to_empty_array(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::checkbox('tags')->options(['a' => 'A']),
        ]);
        $ctx = $this->ctx(['tags' => null], $g);
        $this->stage->handle($ctx);
        $this->assertSame([], $ctx->fields['tags']);
    }

    // -------------------------------------------------------------------------
    // Group field — sub-fields passed through as-is
    // BuildsFieldMap only includes top-level fields (by design), so sub-field
    // values are NOT coerced — they pass through with their original types.
    // -------------------------------------------------------------------------

    public function test_group_array_value_is_preserved_intact(): void
    {
        $g = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::object('meta')->fields([
                Field::number('views'),
                Field::text('title'),
            ]),
        ]);

        $input = ['views' => '99', 'title' => 'Hello'];
        $ctx   = $this->ctx(['meta' => $input], $g);
        $this->stage->handle($ctx);

        // Sub-field '99' is NOT coerced to int — it passes through unchanged.
        $this->assertSame($input, $ctx->fields['meta']);
    }

    public function test_group_non_array_value_becomes_empty_array(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::object('meta')->fields([Field::text('name')]),
        ]);
        $ctx = $this->ctx(['meta' => 'not-an-array'], $g);
        $this->stage->handle($ctx);

        $this->assertSame([], $ctx->fields['meta']);
    }

    // -------------------------------------------------------------------------
    // Repeater field — rows preserved as-is (no recursive coercion)
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Number: int vs float distinction
    // -------------------------------------------------------------------------

    public function test_integer_string_coerces_to_int_not_float(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('n')]);
        $ctx = $this->ctx(['n' => '10'], $g);
        $this->stage->handle($ctx);

        $this->assertIsInt($ctx->fields['n']);
        $this->assertSame(10, $ctx->fields['n']);
    }

    public function test_decimal_string_coerces_to_float(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::number('price')]);
        $ctx = $this->ctx(['price' => '29.95'], $g);
        $this->stage->handle($ctx);

        $this->assertIsFloat($ctx->fields['price']);
        $this->assertSame(29.95, $ctx->fields['price']);
    }

    // -------------------------------------------------------------------------
    // Missing key — field absent from payload
    // -------------------------------------------------------------------------

    public function test_missing_key_is_not_added(): void
    {
        $g   = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([Field::text('title')]);
        $ctx = $this->ctx([], $g);
        $this->stage->handle($ctx);

        // Coercion does not inject defaults for absent keys — that is the
        // pipeline caller's responsibility (e.g. MetaBoxRenderer sets defaults).
        $this->assertArrayNotHasKey('title', $ctx->fields);
    }
}
