<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Migration;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Core\Migration\MigrationEngine;
use CtrlField\Core\Migration\MigrationInterface;
use CtrlField\Core\Migration\MigrationRecord;
use CtrlField\Core\Migration\MigrationResult;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use CtrlField\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class MigrationEngineTest extends TestCase
{
    protected function setUp(): void
    {
        MigrationEngine::reset();
        CacheAdapter::flush();
    }

    private function makeAdapter(): PostMetaAdapter
    {
        return new PostMetaAdapter(new MigrationInMemoryDriver(), currentVersion: 99);
    }

    // -------------------------------------------------------------------------
    // No migrations needed
    // -------------------------------------------------------------------------

    public function test_already_current_returns_success_without_writing(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $record  = new MigrationRecord(1, 1, ['name' => 'Acme']); // stored at v1, migration targets v1 → no-op
        $results = $engine->run([$record], $adapter);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->wasAlreadyCurrent());
        $this->assertEmpty($driver->updateCallCount === 0 ? [] : ['update was called']);
    }

    public function test_empty_migrations_list_returns_current_result(): void
    {
        $adapter = $this->makeAdapter();
        $engine  = new MigrationEngine([]);
        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->wasAlreadyCurrent());
    }

    // -------------------------------------------------------------------------
    // Successful migration
    // -------------------------------------------------------------------------

    public function test_single_migration_transforms_data(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->success);
        $this->assertSame(0, $results[0]->fromVersion);
        $this->assertSame(1, $results[0]->toVersion);
        $this->assertArrayHasKey('migrated_at_v1', $results[0]->data);
    }

    public function test_single_migration_persists_to_adapter(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $engine->run([$record], $adapter);

        CacheAdapter::flush();
        $raw = $adapter->loadRaw(1);
        $this->assertSame(1, $raw['schema_version']);
        $this->assertArrayHasKey('migrated_at_v1', $raw['fields']);
    }

    public function test_chained_migrations_run_in_order(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV2Migration(), new BumpToV1Migration()]);
        // Intentionally out of order — engine must sort them

        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter);

        $this->assertSame(2, $results[0]->toVersion);
        $this->assertArrayHasKey('migrated_at_v1', $results[0]->data);
        $this->assertArrayHasKey('migrated_at_v2', $results[0]->data);
    }

    public function test_only_applicable_migrations_run(): void
    {
        // Record is at v1 — only v2 migration should run
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV1Migration(), new BumpToV2Migration()]);

        $record  = new MigrationRecord(1, 1, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter);

        $this->assertSame(2, $results[0]->toVersion);
        $this->assertArrayNotHasKey('migrated_at_v1', $results[0]->data);
        $this->assertArrayHasKey('migrated_at_v2', $results[0]->data);
    }

    public function test_multiple_records_all_processed(): void
    {
        $adapter = $this->makeAdapter();
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $records = [
            new MigrationRecord(1, 0, ['name' => 'Post 1']),
            new MigrationRecord(2, 0, ['name' => 'Post 2']),
            new MigrationRecord(3, 0, ['name' => 'Post 3']),
        ];

        $results = $engine->run($records, $adapter);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->success);
        $this->assertTrue($results[1]->success);
        $this->assertTrue($results[2]->success);
    }

    // -------------------------------------------------------------------------
    // Dry run
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_write_to_adapter(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter, dryRun: true);

        $this->assertTrue($results[0]->success);
        $this->assertSame(1, $results[0]->toVersion);
        $this->assertEmpty($driver->store, 'Dry run must not write to the adapter.');
    }

    public function test_dry_run_returns_transformed_data_in_result(): void
    {
        $adapter = $this->makeAdapter();
        $engine  = new MigrationEngine([new BumpToV1Migration()]);

        $record  = new MigrationRecord(1, 0, ['name' => 'Acme']);
        $results = $engine->run([$record], $adapter, dryRun: true);

        $this->assertArrayHasKey('migrated_at_v1', $results[0]->data);
    }

    // -------------------------------------------------------------------------
    // Failure and rollback
    // -------------------------------------------------------------------------

    public function test_failing_migration_returns_failure_result(): void
    {
        $adapter = $this->makeAdapter();
        $engine  = new MigrationEngine([new FailingMigration()]);

        $record  = new MigrationRecord(1, 0, ['name' => 'Original']);
        $results = $engine->run([$record], $adapter);

        $this->assertFalse($results[0]->success);
        $this->assertNotEmpty($results[0]->error);
    }

    public function test_failing_migration_restores_original_data(): void
    {
        $driver  = new MigrationInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 99);

        // Pre-save original data (simulating existing post)
        $adapter->save(1, ['name' => 'Original'], 0);
        CacheAdapter::flush();

        $engine  = new MigrationEngine([new FailingMigration()]);
        $record  = new MigrationRecord(1, 0, ['name' => 'Original']);
        $engine->run([$record], $adapter);

        CacheAdapter::flush();
        $raw = $adapter->loadRaw(1);
        $this->assertSame(0, $raw['schema_version']);
        $this->assertSame('Original', $raw['fields']['name']);
    }

    public function test_failure_stops_current_record_but_continues_others(): void
    {
        $adapter = $this->makeAdapter();
        $engine  = new MigrationEngine([new FailingMigration()]);

        $records = [
            new MigrationRecord(1, 0, ['name' => 'Fail']),
            new MigrationRecord(2, 0, ['name' => 'Also fails']),
        ];

        $results = $engine->run($records, $adapter);

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]->success);
        $this->assertFalse($results[1]->success);
    }

    // -------------------------------------------------------------------------
    // Progress callback
    // -------------------------------------------------------------------------

    public function test_on_progress_called_for_each_record(): void
    {
        $adapter   = $this->makeAdapter();
        $engine    = new MigrationEngine([new BumpToV1Migration()]);
        $callCount = 0;

        $engine->run(
            records:    [
                new MigrationRecord(1, 0, []),
                new MigrationRecord(2, 0, []),
            ],
            adapter:    $adapter,
            onProgress: static function (MigrationResult $r) use (&$callCount): void {
                $callCount++;
            },
        );

        $this->assertSame(2, $callCount);
    }

    // -------------------------------------------------------------------------
    // Static registry
    // -------------------------------------------------------------------------

    public function test_register_and_registered(): void
    {
        MigrationEngine::register(new BumpToV1Migration());
        MigrationEngine::register(new BumpToV2Migration());

        $this->assertCount(2, MigrationEngine::registered());
    }

    public function test_registered_sorted_by_version(): void
    {
        MigrationEngine::register(new BumpToV2Migration());
        MigrationEngine::register(new BumpToV1Migration());

        $versions = array_map(
            static fn(MigrationInterface $m): int => $m->version(),
            MigrationEngine::registered()
        );

        $this->assertSame([1, 2], $versions);
    }

    public function test_reset_clears_registry(): void
    {
        MigrationEngine::register(new BumpToV1Migration());
        MigrationEngine::reset();

        $this->assertEmpty(MigrationEngine::registered());
    }
}

// ---------------------------------------------------------------------------
// Migration stubs
// ---------------------------------------------------------------------------

class BumpToV1Migration implements MigrationInterface
{
    public function version(): int { return 1; }

    public function up(array $data): array
    {
        return array_merge($data, ['migrated_at_v1' => true]);
    }
}

class BumpToV2Migration implements MigrationInterface
{
    public function version(): int { return 2; }

    public function up(array $data): array
    {
        return array_merge($data, ['migrated_at_v2' => true]);
    }
}

class FailingMigration implements MigrationInterface
{
    public function version(): int { return 1; }

    public function up(array $data): array
    {
        throw new \RuntimeException('Migration failed intentionally.');
    }
}

// ---------------------------------------------------------------------------
// In-memory storage driver
// ---------------------------------------------------------------------------

class MigrationInMemoryDriver implements PostMetaDriverInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $store = [];

    public int $updateCallCount = 0;

    public function update(int $postId, string $key, mixed $value): void
    {
        $this->store[$postId][$key] = $value;
        $this->updateCallCount++;
    }

    public function get(int $postId, string $key): mixed
    {
        return $this->store[$postId][$key] ?? '';
    }

    public function delete(int $postId, string $key): void
    {
        unset($this->store[$postId][$key]);
    }
}
