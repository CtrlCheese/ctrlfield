<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\Stages\CapabilityCheckStage;
use CtrlField\Core\Security\Contracts\CapabilityCheckerInterface;
use PHPUnit\Framework\TestCase;

class CapabilityCheckStageTest extends TestCase
{
    public function test_passes_when_user_has_capability(): void
    {
        $stage   = new CapabilityCheckStage(new AlwaysCapable());
        $context = new PipelineContext(1, []);

        $stage->handle($context); // must not throw

        $this->assertTrue(true);
    }

    public function test_throws_when_user_cannot_edit_this_post(): void
    {
        // Can edit posts in general (an author), but not this one.
        $stage = new CapabilityCheckStage(new CapableExceptThisPost());

        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('You do not have permission to edit this post.');
        $stage->handle(new PipelineContext(42, []));
    }

    public function test_throws_when_user_lacks_capability(): void
    {
        $stage   = new CapabilityCheckStage(new NeverCapable());
        $context = new PipelineContext(1, []);

        $this->expectException(PipelineException::class);

        $stage->handle($context);
    }

    public function test_exception_has_correct_stage_and_code(): void
    {
        $stage   = new CapabilityCheckStage(new NeverCapable(), 'manage_options');
        $context = new PipelineContext(1, []);

        try {
            $stage->handle($context);
            $this->fail('Expected PipelineException');
        } catch (PipelineException $e) {
            $this->assertSame('INSUFFICIENT_CAPABILITY', $e->errorCode);
            $this->assertSame(CapabilityCheckStage::NAME, $e->stageName);
            $this->assertStringContainsString('manage_options', $e->getMessage());
        }
    }
}

final class CapableExceptThisPost implements CapabilityCheckerInterface
{
    public function currentUserCan(string $capability, int ...$args): bool { return $capability !== 'edit_post'; }
}

class AlwaysCapable implements CapabilityCheckerInterface
{
    public function currentUserCan(string $capability, int ...$args): bool { return true; }
}

class NeverCapable implements CapabilityCheckerInterface
{
    public function currentUserCan(string $capability, int ...$args): bool { return false; }
}
