<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline;

use FieldForge\Core\Pipeline\PipelineException;
use PHPUnit\Framework\TestCase;

class PipelineExceptionTest extends TestCase
{
    public function test_stores_structured_fields(): void
    {
        $ex = new PipelineException(
            errorCode:    'REQUIRED_FIELD',
            errorMessage: "Field 'name' is required.",
            fieldKey:     'name',
            stageName:    'rules_verification',
        );

        $this->assertSame('REQUIRED_FIELD', $ex->errorCode);
        $this->assertSame("Field 'name' is required.", $ex->getMessage());
        $this->assertSame('name', $ex->fieldKey);
        $this->assertSame('rules_verification', $ex->stageName);
    }

    public function test_to_array_matches_error_contract(): void
    {
        $ex = new PipelineException('CODE', 'Message.', 'field_key', 'stage_name');

        $this->assertSame([
            'code'    => 'CODE',
            'message' => 'Message.',
            'field'   => 'field_key',
            'stage'   => 'stage_name',
        ], $ex->toArray());
    }

    public function test_field_key_can_be_null(): void
    {
        $ex = new PipelineException('NONCE', 'Bad nonce.', null, 'nonce_validation');

        $this->assertNull($ex->fieldKey);
        $this->assertNull($ex->toArray()['field']);
    }

    public function test_to_array_is_json_serializable(): void
    {
        $ex   = new PipelineException('CODE', 'Msg', 'key', 'stage');
        $json = json_encode($ex->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('CODE', $json);
        $this->assertStringContainsString('key', $json);
    }

    public function test_previous_exception_is_chained(): void
    {
        $original = new \JsonException('bad json');
        $ex       = new PipelineException('MALFORMED_JSON', 'Parse error.', null, 'json_decode', $original);

        $this->assertSame($original, $ex->getPrevious());
    }
}
