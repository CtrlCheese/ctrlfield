<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Storage;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use CtrlField\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class PostMetaAdapterTest extends TestCase
{
    private InMemoryPostMetaDriver $driver;
    private PostMetaAdapter        $adapter;

    protected function setUp(): void
    {
        CacheAdapter::flush();
        $this->driver  = new InMemoryPostMetaDriver();
        $this->adapter = new PostMetaAdapter($this->driver);
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function test_save_backs_up_unreadable_data_before_overwriting(): void
    {
        $corrupt = '{"schema_version":1,"fields":{"name":"Agência "Sol""}}'; // lost its backslashes
        $this->driver->update(7, PostMetaAdapter::META_KEY, $corrupt);

        $this->adapter->save(7, ['name' => 'new'], 1);

        $this->assertSame($corrupt, $this->driver->get(7, PostMetaAdapter::BACKUP_KEY));
        $this->assertSame(['name' => 'new'], $this->adapter->load(7));
    }

    public function test_save_does_not_back_up_readable_data(): void
    {
        $this->adapter->save(8, ['a' => 1], 1);
        CacheAdapter::flush();
        $this->adapter->save(8, ['a' => 2], 1);

        $this->assertSame('', $this->driver->get(8, PostMetaAdapter::BACKUP_KEY));
    }

    public function test_save_writes_json_blob_to_meta_key(): void
    {
        $this->adapter->save(1, ['client_name' => 'Acme'], 1);

        $stored = $this->driver->store[1][PostMetaAdapter::META_KEY] ?? null;

        $this->assertIsString($stored);
        $decoded = json_decode($stored, true);
        $this->assertSame('Acme', $decoded['fields']['client_name']);
    }

    public function test_save_payload_contains_schema_version(): void
    {
        $this->adapter->save(1, ['name' => 'Test'], 3);

        $stored  = $this->driver->store[1][PostMetaAdapter::META_KEY];
        $decoded = json_decode($stored, true);

        $this->assertSame(3, $decoded['schema_version']);
    }

    public function test_save_writes_indexed_fields_as_separate_rows(): void
    {
        $this->adapter->save(
            id: 1,
            data: ['client_name' => 'Acme', 'status' => 'active'],
            version: 1,
            indexedFields: ['client_name' => 'Acme']
        );

        $indexKey = PostMetaAdapter::INDEX_KEY_PREFIX . 'client_name';

        $this->assertArrayHasKey($indexKey, $this->driver->store[1]);
        $this->assertSame('Acme', $this->driver->store[1][$indexKey]);
    }

    public function test_save_does_not_write_index_rows_when_none_provided(): void
    {
        $this->adapter->save(1, ['name' => 'Test'], 1);

        $indexKeys = array_filter(
            array_keys($this->driver->store[1] ?? []),
            static fn(string $k) => str_starts_with($k, PostMetaAdapter::INDEX_KEY_PREFIX)
        );

        $this->assertEmpty($indexKeys);
    }

    public function test_save_can_write_multiple_indexed_fields(): void
    {
        $this->adapter->save(
            id: 1,
            data: ['client' => 'Acme', 'status' => 'active', 'score' => 99],
            version: 1,
            indexedFields: ['client' => 'Acme', 'status' => 'active']
        );

        $this->assertArrayHasKey(PostMetaAdapter::INDEX_KEY_PREFIX . 'client', $this->driver->store[1]);
        $this->assertArrayHasKey(PostMetaAdapter::INDEX_KEY_PREFIX . 'status', $this->driver->store[1]);
        $this->assertArrayNotHasKey(PostMetaAdapter::INDEX_KEY_PREFIX . 'score', $this->driver->store[1]);
    }

    public function test_save_invalidates_cache(): void
    {
        $this->adapter->save(1, ['name' => 'Before'], 1);
        $this->adapter->load(1); // populates cache

        $this->adapter->save(1, ['name' => 'After'], 1);

        // Next load must go to driver, not return stale cached value
        $this->assertSame(['name' => 'After'], $this->adapter->load(1));
    }

    public function test_save_with_nested_data_serializes_correctly(): void
    {
        $data = [
            'schedule' => [
                ['phase_name' => 'Discovery', 'due_date' => '2026-01-01'],
                ['phase_name' => 'Build', 'due_date' => '2026-03-01'],
            ],
        ];

        $this->adapter->save(1, $data, 1);

        $loaded = $this->adapter->load(1);

        $this->assertCount(2, $loaded['schedule']);
        $this->assertSame('Discovery', $loaded['schedule'][0]['phase_name']);
    }

    public function test_save_requires_integer_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->save('not_an_int', [], 1);
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    public function test_load_returns_fields_from_saved_data(): void
    {
        $this->adapter->save(1, ['client_name' => 'Acme Corp'], 1);

        $loaded = $this->adapter->load(1);

        $this->assertIsArray($loaded);
        $this->assertSame('Acme Corp', $loaded['client_name']);
    }

    public function test_load_returns_null_when_no_data_exists(): void
    {
        $result = $this->adapter->load(99);

        $this->assertNull($result);
    }

    public function test_load_second_call_hits_static_cache(): void
    {
        $this->adapter->save(1, ['name' => 'Cached'], 1);

        $this->adapter->load(1); // first call — goes to driver

        $getsBefore = $this->driver->getCallCount;

        $this->adapter->load(1); // second call — must hit cache

        $this->assertSame($getsBefore, $this->driver->getCallCount, 'Second load() must not call the driver.');
    }

    public function test_load_returns_null_for_invalid_json(): void
    {
        $this->driver->store[1][PostMetaAdapter::META_KEY] = '{broken json';

        $result = $this->adapter->load(1);

        $this->assertNull($result);
    }

    public function test_load_returns_null_for_missing_fields_key(): void
    {
        $this->driver->store[1][PostMetaAdapter::META_KEY] = json_encode(['schema_version' => 1]);

        $result = $this->adapter->load(1);

        $this->assertNull($result);
    }

    public function test_load_requires_integer_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->load('not_an_int');
    }

    // -------------------------------------------------------------------------
    // Round-trip
    // -------------------------------------------------------------------------

    public function test_full_round_trip_preserves_all_types(): void
    {
        $data = [
            'text'   => 'Hello',
            'number' => 42,
            'float'  => 3.14,
            'bool'   => true,
            'null'   => null,
            'array'  => ['a', 'b'],
        ];

        $this->adapter->save(1, $data, 1);
        $loaded = $this->adapter->load(1);

        $this->assertSame($data, $loaded);
    }
}

// ---------------------------------------------------------------------------
// In-memory driver stub
// ---------------------------------------------------------------------------

class InMemoryPostMetaDriver implements PostMetaDriverInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $store = [];

    public int $updateCallCount = 0;
    public int $getCallCount    = 0;

    public function update(int $postId, string $key, mixed $value): void
    {
        $this->store[$postId][$key] = $value;
        $this->updateCallCount++;
    }

    public function get(int $postId, string $key): mixed
    {
        $this->getCallCount++;
        return $this->store[$postId][$key] ?? '';
    }

    public function delete(int $postId, string $key): void
    {
        unset($this->store[$postId][$key]);
    }
}
