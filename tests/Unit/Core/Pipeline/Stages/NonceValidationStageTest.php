<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\Stages\NonceValidationStage;
use FieldForge\Core\Security\Contracts\NonceValidatorInterface;
use PHPUnit\Framework\TestCase;

class NonceValidationStageTest extends TestCase
{
    public function test_passes_with_valid_nonce(): void
    {
        $stage   = new NonceValidationStage(new AlwaysValidNonce());
        $context = new PipelineContext(1, ['_fieldforge_nonce' => 'valid_nonce']);

        $stage->handle($context); // must not throw

        $this->assertTrue(true);
    }

    public function test_throws_with_invalid_nonce(): void
    {
        $stage   = new NonceValidationStage(new AlwaysInvalidNonce());
        $context = new PipelineContext(1, ['_fieldforge_nonce' => 'bad_nonce']);

        $this->expectException(PipelineException::class);

        $stage->handle($context);
    }

    public function test_throws_when_nonce_field_is_missing(): void
    {
        $stage   = new NonceValidationStage(new AlwaysInvalidNonce());
        $context = new PipelineContext(1, []); // no nonce key

        $this->expectException(PipelineException::class);

        $stage->handle($context);
    }

    public function test_exception_has_correct_stage_and_code(): void
    {
        $stage   = new NonceValidationStage(new AlwaysInvalidNonce());
        $context = new PipelineContext(1, []);

        try {
            $stage->handle($context);
            $this->fail('Expected PipelineException');
        } catch (PipelineException $e) {
            $this->assertSame('INVALID_NONCE', $e->errorCode);
            $this->assertSame(NonceValidationStage::NAME, $e->stageName);
            $this->assertNull($e->fieldKey);
        }
    }
}

class AlwaysValidNonce implements NonceValidatorInterface
{
    public function verify(string $nonce, string $action): bool { return true; }
}

class AlwaysInvalidNonce implements NonceValidatorInterface
{
    public function verify(string $nonce, string $action): bool { return false; }
}
