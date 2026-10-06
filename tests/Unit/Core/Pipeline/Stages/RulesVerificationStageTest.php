<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\RulesVerificationStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class RulesVerificationStageTest extends TestCase
{
    private RulesVerificationStage $stage;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        $this->stage = new RulesVerificationStage();
    }

    private function makeContext(array $fields, FieldGroup $group): PipelineContext
    {
        $ctx         = new PipelineContext(1, [], [$group]);
        $ctx->fields = $fields;
        return $ctx;
    }

    public function test_passes_when_all_required_fields_present(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::text('name')->required(),
        ]);
        $context = $this->makeContext(['name' => 'Acme'], $group);

        $this->stage->handle($context); // must not throw

        $this->assertTrue(true);
    }

    public function test_throws_when_required_field_is_empty_string(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::text('name')->required(),
        ]);
        $context = $this->makeContext(['name' => ''], $group);

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_throws_when_required_field_is_missing(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::text('name')->required(),
        ]);
        $context = $this->makeContext([], $group);

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_exception_carries_field_key_and_stage(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::text('client_name')->required(),
        ]);
        $context = $this->makeContext([], $group);

        try {
            $this->stage->handle($context);
            $this->fail('Expected exception');
        } catch (PipelineException $e) {
            $this->assertSame('REQUIRED_FIELD', $e->errorCode);
            $this->assertSame('client_name', $e->fieldKey);
            $this->assertSame(RulesVerificationStage::NAME, $e->stageName);
        }
    }

    public function test_passes_select_with_valid_option(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::select('type')->options(['a' => 'A', 'b' => 'B']),
        ]);
        $context = $this->makeContext(['type' => 'a'], $group);

        $this->stage->handle($context); // must not throw

        $this->assertTrue(true);
    }

    public function test_throws_select_with_invalid_option(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::select('type')->options(['a' => 'A', 'b' => 'B']),
        ]);
        $context = $this->makeContext(['type' => 'z'], $group);

        try {
            $this->stage->handle($context);
            $this->fail('Expected exception');
        } catch (PipelineException $e) {
            $this->assertSame('INVALID_OPTION', $e->errorCode);
            $this->assertSame('type', $e->fieldKey);
        }
    }

    public function test_passes_radio_with_valid_option(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::radio('status')->options(['draft' => 'Draft', 'pub' => 'Published']),
        ]);
        $context = $this->makeContext(['status' => 'draft'], $group);

        $this->stage->handle($context);

        $this->assertTrue(true);
    }

    public function test_throws_checkbox_with_invalid_option(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::checkbox('features')->options(['a' => 'A', 'b' => 'B']),
        ]);
        $context = $this->makeContext(['features' => ['a', 'z']], $group);

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_empty_select_value_skips_option_check(): void
    {
        // An empty value for a non-required select should pass (no value submitted)
        $group = FieldGroup::make('g')->where('post_type', '==', 'p')->fields([
            Field::select('type')->options(['a' => 'A']),
        ]);
        $context = $this->makeContext(['type' => ''], $group);

        $this->stage->handle($context);

        $this->assertTrue(true);
    }
}
