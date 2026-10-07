<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Stages\PersistenceStage;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use CtrlField\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class PersistenceStageTest extends TestCase
{
    protected function setUp(): void
    {
        CacheAdapter::flush();
    }

    public function test_persists_fields_to_adapter(): void
    {
        $driver  = new PersistenceInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);
        $stage   = new PersistenceStage($adapter);

        $context                = new PipelineContext(1, []);
        $context->fields        = ['client_name' => 'Acme', 'status' => 'active'];
        $context->indexedFields = ['client_name' => 'Acme'];

        $stage->handle($context);

        // Verify data was written
        $loaded = $adapter->load(1);
        $this->assertSame('Acme', $loaded['client_name']);
        $this->assertSame('active', $loaded['status']);
    }

    public function test_a_partial_save_keeps_the_other_stored_fields(): void
    {
        $driver  = new PersistenceInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);
        $stage   = new PersistenceStage($adapter);

        $first         = new PipelineContext(1, []);
        $first->fields = ['client_name' => 'Acme', 'status' => 'active'];
        $stage->handle($first);
        \CtrlField\Core\Cache\CacheAdapter::flush();

        // Second save carries only another group's field (one meta box, REST patch).
        $second         = new PipelineContext(1, []);
        $second->fields = ['gallery' => [3, 4], 'status' => 'done'];
        $stage->handle($second);
        \CtrlField\Core\Cache\CacheAdapter::flush();

        $this->assertSame(
            ['client_name' => 'Acme', 'status' => 'done', 'gallery' => [3, 4]],
            $adapter->load(1),
        );
    }

    public function test_persists_indexed_fields_as_separate_rows(): void
    {
        $driver  = new PersistenceInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);
        $stage   = new PersistenceStage($adapter);

        $context                = new PipelineContext(1, []);
        $context->fields        = ['client_name' => 'Acme'];
        $context->indexedFields = ['client_name' => 'Acme'];

        $stage->handle($context);

        $indexKey = PostMetaAdapter::INDEX_KEY_PREFIX . 'client_name';
        $this->assertArrayHasKey($indexKey, $driver->store[1]);
        $this->assertSame('Acme', $driver->store[1][$indexKey]);
    }

    public function test_persists_schema_version(): void
    {
        $driver  = new PersistenceInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);
        $stage   = new PersistenceStage($adapter);

        $context         = new PipelineContext(1, []);
        $context->fields = ['name' => 'Test'];

        $stage->handle($context);

        $raw     = $driver->store[1][PostMetaAdapter::META_KEY];
        $decoded = json_decode($raw, true);
        $this->assertSame(PersistenceStage::SCHEMA_VERSION, $decoded['schema_version']);
    }

    public function test_empty_fields_persist_without_error(): void
    {
        $driver  = new PersistenceInMemoryDriver();
        $adapter = new PostMetaAdapter($driver);
        $stage   = new PersistenceStage($adapter);

        $context         = new PipelineContext(1, []);
        $context->fields = [];

        $stage->handle($context); // must not throw

        $this->assertTrue(true);
    }
}

class PersistenceInMemoryDriver implements PostMetaDriverInterface
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
