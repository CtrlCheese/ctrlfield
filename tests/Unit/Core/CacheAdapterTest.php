<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core;

use CtrlField\Core\Cache\CacheAdapter;
use PHPUnit\Framework\TestCase;

class CacheAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        CacheAdapter::flush();
    }

    public function test_get_returns_null_for_uncached_key(): void
    {
        $this->assertNull(CacheAdapter::get('nonexistent'));
    }

    public function test_set_and_get_round_trip(): void
    {
        CacheAdapter::set('key', ['name' => 'Acme']);

        $this->assertSame(['name' => 'Acme'], CacheAdapter::get('key'));
    }

    public function test_set_stores_any_type(): void
    {
        CacheAdapter::set('str', 'hello');
        CacheAdapter::set('int', 42);
        CacheAdapter::set('arr', [1, 2, 3]);
        CacheAdapter::set('null_val', null);

        $this->assertSame('hello', CacheAdapter::get('str'));
        $this->assertSame(42, CacheAdapter::get('int'));
        $this->assertSame([1, 2, 3], CacheAdapter::get('arr'));
        $this->assertNull(CacheAdapter::get('null_val'));
    }

    public function test_set_overwrites_existing_value(): void
    {
        CacheAdapter::set('key', 'first');
        CacheAdapter::set('key', 'second');

        $this->assertSame('second', CacheAdapter::get('key'));
    }

    public function test_invalidate_removes_from_static_cache(): void
    {
        CacheAdapter::set('key', 'value');
        CacheAdapter::invalidate('key');

        $this->assertNull(CacheAdapter::get('key'));
    }

    public function test_invalidate_unknown_key_does_not_throw(): void
    {
        CacheAdapter::invalidate('never_set');

        $this->assertTrue(true);
    }

    public function test_flush_clears_all_entries(): void
    {
        CacheAdapter::set('a', 1);
        CacheAdapter::set('b', 2);
        CacheAdapter::set('c', 3);

        CacheAdapter::flush();

        $this->assertNull(CacheAdapter::get('a'));
        $this->assertNull(CacheAdapter::get('b'));
        $this->assertNull(CacheAdapter::get('c'));
        $this->assertEmpty(CacheAdapter::dump());
    }

    public function test_dump_reflects_current_static_cache(): void
    {
        CacheAdapter::set('x', 10);
        CacheAdapter::set('y', 20);

        $dump = CacheAdapter::dump();

        $this->assertArrayHasKey('x', $dump);
        $this->assertArrayHasKey('y', $dump);
        $this->assertSame(10, $dump['x']);
    }

    public function test_second_get_hits_static_cache_not_wp(): void
    {
        // Verify the static array is used on repeated gets (no WP Object Cache in unit env).
        CacheAdapter::set('post:1', ['name' => 'Acme']);

        $first  = CacheAdapter::get('post:1');
        $second = CacheAdapter::get('post:1');

        $this->assertSame($first, $second);
        $this->assertSame(['name' => 'Acme'], $second);
    }
}
