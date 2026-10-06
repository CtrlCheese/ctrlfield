<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\ComputedFieldsStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class ComputedFieldsStageTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_computes_value_from_other_fields(): void
    {
        $group = Field::group('contact')
            ->where('post_type', '==', 'contact')
            ->fields([
                Field::text('first_name')->label('First Name'),
                Field::text('last_name')->label('Last Name'),
                Field::computed('full_name', static fn($f) => trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? ''))),
            ]);
        $group->register();

        $context           = new PipelineContext(1, [], [$group]);
        $context->fields   = ['first_name' => 'John', 'last_name' => 'Doe'];

        (new ComputedFieldsStage())->handle($context);

        $this->assertSame('John Doe', $context->fields['full_name']);
    }

    public function test_computed_field_with_no_source_fields(): void
    {
        $group = Field::group('contact')
            ->where('post_type', '==', 'contact')
            ->fields([
                Field::computed('slug', static fn($f) => 'static-value'),
            ]);
        $group->register();

        $context           = new PipelineContext(1, [], [$group]);
        $context->fields   = [];

        (new ComputedFieldsStage())->handle($context);

        $this->assertSame('static-value', $context->fields['slug']);
    }

    public function test_computed_field_callback_exception_raises_pipeline_exception(): void
    {
        $group = Field::group('contact')
            ->where('post_type', '==', 'contact')
            ->fields([
                Field::computed('bad_field', static function ($f): never {
                    throw new \RuntimeException('Something went wrong');
                }),
            ]);
        $group->register();

        $context = new PipelineContext(1, [], [$group]);

        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('Computed field "bad_field" callback threw');

        (new ComputedFieldsStage())->handle($context);
    }

    public function test_pipeline_exception_has_correct_error_code(): void
    {
        $group = Field::group('contact')
            ->where('post_type', '==', 'contact')
            ->fields([
                Field::computed('bad', static fn() => throw new \RuntimeException('fail')),
            ]);
        $group->register();

        $context = new PipelineContext(1, [], [$group]);

        try {
            (new ComputedFieldsStage())->handle($context);
            $this->fail('Expected PipelineException');
        } catch (PipelineException $e) {
            $this->assertSame('COMPUTED_FIELD_ERROR', $e->errorCode);
            $this->assertSame('bad', $e->fieldKey);
        }
    }

    public function test_skips_non_computed_fields(): void
    {
        $group = Field::group('contact')
            ->where('post_type', '==', 'contact')
            ->fields([
                Field::text('name')->label('Name'),
            ]);
        $group->register();

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['name' => 'Test'];

        (new ComputedFieldsStage())->handle($context);

        $this->assertSame(['name' => 'Test'], $context->fields);
    }
}
