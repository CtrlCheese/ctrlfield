<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Storage;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Storage\Drivers\OptionsDriverInterface;
use FieldForge\Storage\OptionsAdapter;
use PHPUnit\Framework\TestCase;

class OptionsAdapterTest extends TestCase
{
    private InMemoryOptionsDriver $driver;
    private OptionsAdapter        $adapter;

    protected function setUp(): void
    {
        CacheAdapter::flush();
        $this->driver  = new InMemoryOptionsDriver();
        $this->adapter = new OptionsAdapter($this->driver);
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function test_save_writes_to_per_page_key(): void
    {
        $this->adapter->save('theme_settings', ['tagline' => 'Hello'], 1);

        $expectedKey = OptionsAdapter::KEY_PREFIX . 'theme_settings';

        $this->assertArrayHasKey($expectedKey, $this->driver->store);
    }

    public function test_save_payload_is_valid_json_with_schema_version(): void
    {
        $this->adapter->save('theme_settings', ['tagline' => 'Test'], 2);

        $raw     = $this->driver->store[OptionsAdapter::KEY_PREFIX . 'theme_settings']['value'];
        $decoded = json_decode($raw, true);

        $this->assertSame(2, $decoded['schema_version']);
        $this->assertSame('Test', $decoded['fields']['tagline']);
    }

    public function test_autoload_is_yes_for_small_payload(): void
    {
        $this->adapter->save('theme_settings', ['tagline' => 'Short'], 1);

        $autoload = $this->driver->store[OptionsAdapter::KEY_PREFIX . 'theme_settings']['autoload'];

        $this->assertSame('yes', $autoload);
    }

    public function test_autoload_is_no_for_large_payload(): void
    {
        // Generate a payload that exceeds 50 KB
        $bigData = ['content' => str_repeat('x', OptionsAdapter::AUTOLOAD_THRESHOLD + 1)];

        $this->adapter->save('big_page', $bigData, 1);

        $autoload = $this->driver->store[OptionsAdapter::KEY_PREFIX . 'big_page']['autoload'];

        $this->assertSame('no', $autoload);
    }

    public function test_save_invalidates_cache(): void
    {
        $this->adapter->save('theme_settings', ['tagline' => 'Before'], 1);
        $this->adapter->load('theme_settings'); // populate cache

        $this->adapter->save('theme_settings', ['tagline' => 'After'], 1);

        $loaded = $this->adapter->load('theme_settings');
        $this->assertSame('After', $loaded['tagline']);
    }

    public function test_save_ignores_indexed_fields(): void
    {
        $this->adapter->save('page', ['name' => 'Test'], 1, ['name' => 'Test']);

        // OptionsAdapter never writes index rows — verify only the single key exists
        $this->assertCount(1, $this->driver->store);
    }

    public function test_save_requires_string_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->save(42, [], 1);
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    public function test_load_returns_saved_data(): void
    {
        $this->adapter->save('theme_settings', ['logo' => 10, 'tagline' => 'Hi'], 1);

        $loaded = $this->adapter->load('theme_settings');

        $this->assertSame(['logo' => 10, 'tagline' => 'Hi'], $loaded);
    }

    public function test_load_returns_null_when_no_data(): void
    {
        $result = $this->adapter->load('nonexistent_page');

        $this->assertNull($result);
    }

    public function test_load_second_call_hits_static_cache(): void
    {
        $this->adapter->save('theme_settings', ['tagline' => 'Cached'], 1);

        $this->adapter->load('theme_settings'); // first call

        $getsBefore = $this->driver->getCallCount;

        $this->adapter->load('theme_settings'); // second call — must hit cache

        $this->assertSame($getsBefore, $this->driver->getCallCount, 'Second load() must not call the driver.');
    }

    public function test_load_returns_null_for_invalid_json(): void
    {
        $this->driver->store[OptionsAdapter::KEY_PREFIX . 'broken'] = [
            'value'    => '{not valid json',
            'autoload' => 'yes',
        ];

        $this->assertNull($this->adapter->load('broken'));
    }

    public function test_load_requires_string_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adapter->load(42);
    }

    // -------------------------------------------------------------------------
    // Round-trip
    // -------------------------------------------------------------------------

    public function test_different_pages_store_to_separate_keys(): void
    {
        $this->adapter->save('page_a', ['field' => 'A'], 1);
        $this->adapter->save('page_b', ['field' => 'B'], 1);

        $this->assertSame('A', $this->adapter->load('page_a')['field']);
        $this->assertSame('B', $this->adapter->load('page_b')['field']);
    }
}

// ---------------------------------------------------------------------------
// In-memory driver stub
// ---------------------------------------------------------------------------

class InMemoryOptionsDriver implements OptionsDriverInterface
{
    /** @var array<string, array{value: string, autoload: string}> */
    public array $store = [];

    public int $getCallCount = 0;

    public function update(string $key, mixed $value, string $autoload): void
    {
        $this->store[$key] = ['value' => $value, 'autoload' => $autoload];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCallCount++;
        return $this->store[$key]['value'] ?? $default;
    }
}
