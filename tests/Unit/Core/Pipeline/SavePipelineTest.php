<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline;

use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\SavePipeline;
use PHPUnit\Framework\TestCase;

class SavePipelineTest extends TestCase
{
    public function test_process_runs_all_stages_in_order(): void
    {
        $order   = [];
        $context = new PipelineContext(1, []);

        $pipeline = new SavePipeline([
            new OrderRecordingStage('first',  $order),
            new OrderRecordingStage('second', $order),
            new OrderRecordingStage('third',  $order),
        ]);

        $pipeline->process($context);

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function test_process_aborts_on_pipeline_exception(): void
    {
        $order   = [];
        $context = new PipelineContext(1, []);

        $pipeline = new SavePipeline([
            new OrderRecordingStage('first', $order),
            new ThrowingStage(),
            new OrderRecordingStage('third', $order), // must NOT run
        ]);

        $this->expectException(PipelineException::class);

        try {
            $pipeline->process($context);
        } finally {
            $this->assertSame(['first'], $order, 'Stage after exception must not execute.');
        }
    }

    public function test_process_stages_share_context(): void
    {
        $context = new PipelineContext(1, []);

        $pipeline = new SavePipeline([
            new FieldSetterStage('name', 'Acme'),
            new FieldSetterStage('status', 'active'),
        ]);

        $pipeline->process($context);

        $this->assertSame(['name' => 'Acme', 'status' => 'active'], $context->fields);
    }

    public function test_empty_stages_list_is_valid(): void
    {
        $context  = new PipelineContext(1, []);
        $pipeline = new SavePipeline([]);

        $pipeline->process($context);

        $this->assertEmpty($context->fields);
    }
}

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

class OrderRecordingStage implements StageInterface
{
    /** @param array<int, string> $log */
    public function __construct(private string $name, private array &$log) {}

    public function handle(PipelineContext $context): void
    {
        $this->log[] = $this->name;
    }
}

class ThrowingStage implements StageInterface
{
    public function handle(PipelineContext $context): void
    {
        throw new PipelineException('TEST_ABORT', 'Abort.', null, 'test');
    }
}

class FieldSetterStage implements StageInterface
{
    public function __construct(private string $key, private mixed $value) {}

    public function handle(PipelineContext $context): void
    {
        $context->fields[$this->key] = $this->value;
    }
}
