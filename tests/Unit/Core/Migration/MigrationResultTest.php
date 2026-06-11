<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Migration;

use FieldForge\Core\Migration\MigrationResult;
use PHPUnit\Framework\TestCase;

class MigrationResultTest extends TestCase
{
    public function test_success_result_stores_all_fields(): void
    {
        $result = new MigrationResult(
            postId:      42,
            success:     true,
            fromVersion: 0,
            toVersion:   1,
            data:        ['name' => 'Acme'],
        );

        $this->assertSame(42, $result->postId);
        $this->assertTrue($result->success);
        $this->assertSame(0, $result->fromVersion);
        $this->assertSame(1, $result->toVersion);
        $this->assertSame(['name' => 'Acme'], $result->data);
        $this->assertSame('', $result->error);
    }

    public function test_failure_result_stores_error(): void
    {
        $result = new MigrationResult(
            postId:      1,
            success:     false,
            fromVersion: 0,
            toVersion:   0,
            data:        ['name' => 'Original'],
            error:       'Field x not found.',
        );

        $this->assertFalse($result->success);
        $this->assertSame('Field x not found.', $result->error);
    }

    public function test_was_upgraded_true_when_version_increased(): void
    {
        $result = new MigrationResult(1, true, 0, 1, []);

        $this->assertTrue($result->wasUpgraded());
        $this->assertFalse($result->wasAlreadyCurrent());
    }

    public function test_was_already_current_true_when_version_unchanged(): void
    {
        $result = new MigrationResult(1, true, 1, 1, []);

        $this->assertTrue($result->wasAlreadyCurrent());
        $this->assertFalse($result->wasUpgraded());
    }

    public function test_failed_result_is_not_was_upgraded(): void
    {
        $result = new MigrationResult(1, false, 0, 1, [], 'Error.');

        $this->assertFalse($result->wasUpgraded());
    }
}
