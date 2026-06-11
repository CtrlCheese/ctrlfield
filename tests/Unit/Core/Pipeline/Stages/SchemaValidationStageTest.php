<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline\Stages;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\Stages\SchemaValidationStage;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class SchemaValidationStageTest extends TestCase
{
    private SchemaValidationStage $stage;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        $this->stage = new SchemaValidationStage();
    }

    private function makeGroup(): FieldGroup
    {
        return FieldGroup::make('g')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name'),
                Field::select('project_type'),
            ]);
    }

    public function test_allows_known_keys_through(): void
    {
        $context         = new PipelineContext(1, [], [$this->makeGroup()]);
        $context->fields = ['client_name' => 'Acme', 'project_type' => 'external'];

        $this->stage->handle($context);

        $this->assertArrayHasKey('client_name', $context->fields);
        $this->assertArrayHasKey('project_type', $context->fields);
    }

    public function test_strips_unknown_keys(): void
    {
        $context         = new PipelineContext(1, [], [$this->makeGroup()]);
        $context->fields = [
            'client_name'  => 'Acme',
            'unknown_key'  => 'should be removed',
            'project_type' => 'external',
        ];

        $this->stage->handle($context);

        $this->assertArrayNotHasKey('unknown_key', $context->fields);
        $this->assertArrayHasKey('client_name', $context->fields);
    }

    public function test_throws_when_no_field_groups(): void
    {
        $context         = new PipelineContext(1, [], []);
        $context->fields = ['any' => 'value'];

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_exception_has_correct_stage_and_code(): void
    {
        $context = new PipelineContext(1, [], []);

        try {
            $this->stage->handle($context);
        } catch (PipelineException $e) {
            $this->assertSame('NO_SCHEMA', $e->errorCode);
            $this->assertSame(SchemaValidationStage::NAME, $e->stageName);
        }
    }

    public function test_empty_payload_with_valid_schema_is_allowed(): void
    {
        $context         = new PipelineContext(1, [], [$this->makeGroup()]);
        $context->fields = [];

        $this->stage->handle($context);

        $this->assertEmpty($context->fields);
    }

    public function test_merges_keys_from_multiple_field_groups(): void
    {
        $group1 = FieldGroup::make('g1')
            ->where('post_type', '==', 'p')
            ->fields([Field::text('field_a')]);

        $group2 = FieldGroup::make('g2')
            ->where('post_type', '==', 'p')
            ->fields([Field::text('field_b')]);

        $context         = new PipelineContext(1, [], [$group1, $group2]);
        $context->fields = ['field_a' => 'A', 'field_b' => 'B', 'unknown' => 'X'];

        $this->stage->handle($context);

        $this->assertArrayHasKey('field_a', $context->fields);
        $this->assertArrayHasKey('field_b', $context->fields);
        $this->assertArrayNotHasKey('unknown', $context->fields);
    }
}
