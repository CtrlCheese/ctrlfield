<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Core\Pipeline\Stages\ComputedFieldsStage;
use FieldForge\Core\Pipeline\Stages\JsonDecodeStage;
use FieldForge\Core\Pipeline\Stages\PersistenceStage;
use FieldForge\Core\Pipeline\Stages\SanitizationStage;
use FieldForge\Core\Pipeline\Stages\SchemaValidationStage;
use FieldForge\Core\Pipeline\Stages\TypeCoercionStage;
use FieldForge\Core\Security\Contracts\CapabilityCheckerInterface;
use FieldForge\Core\Security\Contracts\NonceValidatorInterface;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Storage\Drivers\PostMetaDriverInterface;
use FieldForge\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class ComputedFieldPipelineTest extends TestCase
{
    private PostMetaAdapter $adapter;
    private ComputedPipelineInMemoryDriver $driver;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        CacheAdapter::flush();
        $this->driver  = new ComputedPipelineInMemoryDriver();
        $this->adapter = new PostMetaAdapter($this->driver);
    }

    private function makeDataPipeline(): SavePipeline
    {
        return new SavePipeline([
            new JsonDecodeStage(),
            new SchemaValidationStage(),
            new TypeCoercionStage(),
            new SanitizationStage($this->adapter),
            new ComputedFieldsStage(),
            new PersistenceStage($this->adapter),
        ]);
    }

    // -------------------------------------------------------------------------
    // ComputedField is calculated after sanitization
    // -------------------------------------------------------------------------

    public function test_computed_field_value_is_persisted(): void
    {
        $group = FieldGroup::make('product')
            ->where('post_type', '==', 'product')
            ->fields([
                Field::number('price'),
                Field::number('tax_rate'),
                Field::computed('total_price', function (array $fields): float {
                    return ($fields['price'] ?? 0) * (1 + ($fields['tax_rate'] ?? 0) / 100);
                }),
            ]);

        $payload = json_encode(['price' => 100, 'tax_rate' => 20]);
        $ctx     = new PipelineContext(1, ['fieldforge_payload' => $payload], [$group]);

        $this->makeDataPipeline()->process($ctx);

        CacheAdapter::flush();
        $stored = $this->adapter->load(1);

        $this->assertEquals(120.0, $stored['total_price']);
    }

    public function test_computed_field_with_index_writes_index_meta_row(): void
    {
        $group = FieldGroup::make('order')
            ->where('post_type', '==', 'order')
            ->fields([
                Field::number('subtotal'),
                Field::number('shipping'),
                Field::computed('grand_total', function (array $fields): float {
                    return ($fields['subtotal'] ?? 0) + ($fields['shipping'] ?? 0);
                })->setIndex(true),
            ]);

        $payload = json_encode(['subtotal' => 50, 'shipping' => 10]);
        $ctx     = new PipelineContext(1, ['fieldforge_payload' => $payload], [$group]);

        $this->makeDataPipeline()->process($ctx);

        $indexKey = PostMetaAdapter::INDEX_KEY_PREFIX . 'grand_total';
        $this->assertArrayHasKey($indexKey, $this->driver->store[1] ?? []);
        $this->assertEquals(60.0, $this->driver->store[1][$indexKey]);
    }

    public function test_computed_field_receives_sanitized_values(): void
    {
        $received = [];

        $group = FieldGroup::make('blog')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('title'),
                Field::computed('slug', function (array $fields) use (&$received): string {
                    $received = $fields;
                    return strtolower(str_replace(' ', '-', $fields['title'] ?? ''));
                }),
            ]);

        $payload = json_encode(['title' => '  Hello World  ']);
        $ctx     = new PipelineContext(1, ['fieldforge_payload' => $payload], [$group]);

        $this->makeDataPipeline()->process($ctx);

        // Verify the computed callback received the sanitized (trimmed) value
        $this->assertSame('Hello World', $received['title']);

        CacheAdapter::flush();
        $stored = $this->adapter->load(1);
        $this->assertSame('hello-world', $stored['slug']);
    }

    public function test_computed_field_without_index_not_in_indexed_fields(): void
    {
        $group = FieldGroup::make('item')
            ->where('post_type', '==', 'item')
            ->fields([
                Field::text('name'),
                Field::computed('display_name', fn (array $f): string => strtoupper($f['name'] ?? '')),
                // No ->setIndex(true)
            ]);

        $payload = json_encode(['name' => 'widget']);
        $ctx     = new PipelineContext(1, ['fieldforge_payload' => $payload], [$group]);

        $this->makeDataPipeline()->process($ctx);

        // display_name value should be persisted but NOT in the index
        CacheAdapter::flush();
        $stored = $this->adapter->load(1);

        $this->assertSame('WIDGET', $stored['display_name']);

        $indexKey = PostMetaAdapter::INDEX_KEY_PREFIX . 'display_name';
        $this->assertArrayNotHasKey($indexKey, $this->driver->store[1] ?? []);
    }

    public function test_per_group_required_capability_blocks_save(): void
    {
        $group = FieldGroup::make('settings')
            ->where('post_type', '==', 'page')
            ->requiredCapability('manage_options')
            ->fields([Field::text('site_title')]);

        $caps = new class implements CapabilityCheckerInterface {
            public function currentUserCan(string $cap): bool
            {
                return $cap === 'edit_posts'; // has edit_posts, but NOT manage_options
            }
        };

        $nonce = new class implements NonceValidatorInterface {
            public function verify(string $nonce, string $action): bool { return true; }
        };

        $pipeline = new SavePipeline([
            new \FieldForge\Core\Pipeline\Stages\NonceValidationStage($nonce),
            new \FieldForge\Core\Pipeline\Stages\CapabilityCheckStage($caps),
            new JsonDecodeStage(),
            new SchemaValidationStage(),
            new TypeCoercionStage(),
            new SanitizationStage($this->adapter),
            new ComputedFieldsStage(),
            new PersistenceStage($this->adapter),
        ]);

        $payload = json_encode(['site_title' => 'Hacked']);
        $ctx     = new PipelineContext(1, ['fieldforge_nonce' => 'v', 'fieldforge_payload' => $payload], [$group]);

        $this->expectException(\FieldForge\Core\Pipeline\PipelineException::class);
        $pipeline->process($ctx);
    }
}

class ComputedPipelineInMemoryDriver implements PostMetaDriverInterface
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
