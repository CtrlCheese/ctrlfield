<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Storage;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class PostMetaAdapterVersionTest extends TestCase
{
    protected function setUp(): void
    {
        CacheAdapter::flush();
    }

    // -------------------------------------------------------------------------
    // load() version mismatch (acceptance criterion from CYCLES.md)
    // -------------------------------------------------------------------------

    public function test_load_returns_null_and_logs_error_on_version_mismatch(): void
    {
        $driver  = new VersionCheckInMemoryDriver();
        // Adapter configured to current version = 1
        $adapter = new PostMetaAdapter($driver, currentVersion: 1);

        // Save data with schema_version = 0 (older than current)
        $adapter->save(1, ['name' => 'Acme'], 0);
        CacheAdapter::flush();

        // load() must detect mismatch and return null
        $result = $adapter->load(1);

        $this->assertNull($result, 'load() must return null when stored schema_version < current.');
    }

    public function test_load_returns_data_when_version_matches(): void
    {
        $driver  = new VersionCheckInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 1);

        $adapter->save(1, ['name' => 'Acme'], 1);
        CacheAdapter::flush();

        $result = $adapter->load(1);

        $this->assertSame(['name' => 'Acme'], $result);
    }

    public function test_load_returns_data_when_stored_version_is_higher(): void
    {
        // Stored version > current → still loads (forward compatibility)
        $driver  = new VersionCheckInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 1);

        $adapter->save(1, ['name' => 'Future'], 2);
        CacheAdapter::flush();

        $result = $adapter->load(1);

        $this->assertSame(['name' => 'Future'], $result);
    }

    public function test_load_treats_missing_schema_version_as_zero(): void
    {
        $driver = new VersionCheckInMemoryDriver();
        // Manually write a payload without schema_version
        $driver->store[1][PostMetaAdapter::META_KEY] = '{"fields":{"name":"Legacy"}}';

        $adapter = new PostMetaAdapter($driver, currentVersion: 1);
        $result  = $adapter->load(1);

        // Missing schema_version → treated as 0 → mismatch against current 1 → null
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // loadRaw() — bypasses version check
    // -------------------------------------------------------------------------

    public function test_load_raw_returns_full_payload_ignoring_version(): void
    {
        $driver  = new VersionCheckInMemoryDriver();
        $adapter = new PostMetaAdapter($driver, currentVersion: 1);

        // Save with version 0 (would be rejected by load())
        $adapter->save(1, ['name' => 'Acme'], 0);

        $raw = $adapter->loadRaw(1);

        $this->assertNotNull($raw);
        $this->assertSame(0, $raw['schema_version']);
        $this->assertSame('Acme', $raw['fields']['name']);
    }

    public function test_load_raw_returns_null_when_no_data(): void
    {
        $driver  = new VersionCheckInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);

        $this->assertNull($adapter->loadRaw(99));
    }

    public function test_load_raw_returns_null_for_malformed_json(): void
    {
        $driver = new VersionCheckInMemoryDriver();
        $driver->store[1][PostMetaAdapter::META_KEY] = '{not valid json';

        $adapter = new PostMetaAdapter($driver);

        $this->assertNull($adapter->loadRaw(1));
    }

    public function test_load_raw_defaults_schema_version_to_zero_when_missing(): void
    {
        $driver = new VersionCheckInMemoryDriver();
        $driver->store[1][PostMetaAdapter::META_KEY] = '{"fields":{"x":1}}';

        $adapter = new PostMetaAdapter($driver);
        $raw     = $adapter->loadRaw(1);

        $this->assertSame(0, $raw['schema_version']);
    }

    // -------------------------------------------------------------------------
    // SchemaVersion constant
    // -------------------------------------------------------------------------

    public function test_schema_version_current_is_one(): void
    {
        $this->assertSame(1, SchemaVersion::CURRENT);
    }

    public function test_default_adapter_uses_schema_version_current(): void
    {
        $driver  = new VersionCheckInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);

        // Save with CURRENT version → load succeeds
        $adapter->save(1, ['x' => 1], SchemaVersion::CURRENT);
        CacheAdapter::flush();

        $this->assertNotNull($adapter->load(1));
    }
}

// ---------------------------------------------------------------------------
// In-memory driver for version tests
// ---------------------------------------------------------------------------

class VersionCheckInMemoryDriver implements \FieldForge\Storage\Drivers\PostMetaDriverInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $store = [];

    public function update(int $postId, string $key, mixed $value): void
    {
        $this->store[$postId][$key] = $value;
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
