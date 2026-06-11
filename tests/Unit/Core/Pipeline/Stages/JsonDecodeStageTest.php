<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\Stages\JsonDecodeStage;
use PHPUnit\Framework\TestCase;

class JsonDecodeStageTest extends TestCase
{
    private JsonDecodeStage $stage;

    protected function setUp(): void
    {
        $this->stage = new JsonDecodeStage();
    }

    public function test_decodes_valid_json_into_context_fields(): void
    {
        $context = new PipelineContext(1, [
            'fieldforge_payload' => '{"client_name":"Acme","status":"active"}',
        ]);

        $this->stage->handle($context);

        $this->assertSame(['client_name' => 'Acme', 'status' => 'active'], $context->fields);
    }

    public function test_throws_when_payload_is_missing(): void
    {
        $context = new PipelineContext(1, []);

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_throws_on_malformed_json(): void
    {
        $context = new PipelineContext(1, ['fieldforge_payload' => '{bad json']);

        try {
            $this->stage->handle($context);
            $this->fail('Expected PipelineException');
        } catch (PipelineException $e) {
            $this->assertSame('MALFORMED_JSON', $e->errorCode);
            $this->assertSame(JsonDecodeStage::NAME, $e->stageName);
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function test_throws_when_payload_decodes_to_non_array(): void
    {
        $context = new PipelineContext(1, ['fieldforge_payload' => '"just a string"']);

        $this->expectException(PipelineException::class);

        $this->stage->handle($context);
    }

    public function test_handles_nested_data(): void
    {
        $data    = ['schedule' => [['phase' => 'Discovery', 'days' => 5]]];
        $context = new PipelineContext(1, [
            'fieldforge_payload' => json_encode($data),
        ]);

        $this->stage->handle($context);

        $this->assertSame($data, $context->fields);
    }

    public function test_handles_empty_object(): void
    {
        $context = new PipelineContext(1, ['fieldforge_payload' => '{}']);

        $this->stage->handle($context);

        $this->assertSame([], $context->fields);
    }

    public function test_missing_payload_has_correct_error_code(): void
    {
        $context = new PipelineContext(1, []);

        try {
            $this->stage->handle($context);
        } catch (PipelineException $e) {
            $this->assertSame('MISSING_PAYLOAD', $e->errorCode);
        }
    }
}
