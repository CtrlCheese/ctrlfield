<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Core\Pipeline\Stages\CapabilityCheckStage;
use FieldForge\Core\Pipeline\Stages\JsonDecodeStage;
use FieldForge\Core\Pipeline\Stages\NonceValidationStage;
use FieldForge\Core\Pipeline\Stages\PersistenceStage;
use FieldForge\Core\Pipeline\Stages\RulesVerificationStage;
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

/**
 * Full 8-stage pipeline flow tests with stub dependencies.
 * No WP functions involved — purely in-memory.
 */
class SavePipelineFullFlowTest extends TestCase
{
    private PostMetaAdapter $adapter;
    private FullFlowInMemoryDriver $driver;

    protected function setUp(): void
    {
        FieldRegistry::reset();
        CacheAdapter::flush();
        $this->driver  = new FullFlowInMemoryDriver();
        $this->adapter = new PostMetaAdapter($this->driver);
    }

    private function makePipeline(bool $nonceValid = true, bool $capValid = true): SavePipeline
    {
        $nonce = new class($nonceValid) implements NonceValidatorInterface {
            public function __construct(private bool $valid) {}
            public function verify(string $nonce, string $action): bool { return $this->valid; }
        };

        $caps = new class($capValid) implements CapabilityCheckerInterface {
            public function __construct(private bool $valid) {}
            public function currentUserCan(string $capability): bool { return $this->valid; }
        };

        return new SavePipeline([
            new NonceValidationStage($nonce),
            new CapabilityCheckStage($caps),
            new JsonDecodeStage(),
            new SchemaValidationStage(),
            new TypeCoercionStage(),
            new RulesVerificationStage(),
            new SanitizationStage($this->adapter),
            new PersistenceStage($this->adapter),
        ]);
    }

    private function registerGroup(): FieldGroup
    {
        return FieldGroup::make('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->required()->setIndex(true),
                Field::number('year'),
                Field::select('status')->options(['active' => 'Active', 'closed' => 'Closed']),
                Field::checkbox('tags')->options(['php' => 'PHP', 'js' => 'JS']),
            ]);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_full_pipeline_persists_valid_payload(): void
    {
        $group = $this->registerGroup();

        $payload = json_encode([
            'client_name' => 'Acme Corp',
            'year'        => '2024',
            'status'      => 'active',
            'tags'        => ['php', 'js'],
        ]);

        $rawPost = [
            'fieldforge_nonce'   => 'valid',
            'fieldforge_payload' => $payload,
        ];

        $ctx = new PipelineContext(1, $rawPost, [$group]);
        $this->makePipeline()->process($ctx);

        CacheAdapter::flush();
        $stored = $this->adapter->load(1);

        $this->assertIsArray($stored);
        $this->assertSame('Acme Corp', $stored['client_name']);
        $this->assertSame(2024,        $stored['year']);
        $this->assertSame('active',    $stored['status']);
        $this->assertSame(['php', 'js'], $stored['tags']);
    }

    public function test_indexed_field_written_to_separate_meta_key(): void
    {
        $group = $this->registerGroup();

        $payload = json_encode(['client_name' => 'Beta Inc', 'year' => '2023', 'status' => '', 'tags' => []]);
        $ctx     = new PipelineContext(1, ['fieldforge_nonce' => 'v', 'fieldforge_payload' => $payload], [$group]);
        $this->makePipeline()->process($ctx);

        $indexKey = PostMetaAdapter::INDEX_KEY_PREFIX . 'client_name';
        $this->assertArrayHasKey($indexKey, $this->driver->store[1]);
        $this->assertSame('Beta Inc', $this->driver->store[1][$indexKey]);
    }

    // -------------------------------------------------------------------------
    // Invalid nonce — pipeline aborts before writing
    // -------------------------------------------------------------------------

    public function test_invalid_nonce_aborts_pipeline_no_data_written(): void
    {
        $group = $this->registerGroup();

        $payload = json_encode(['client_name' => 'Should not save']);
        $ctx     = new PipelineContext(1, ['fieldforge_nonce' => 'bad', 'fieldforge_payload' => $payload], [$group]);

        try {
            $this->makePipeline(nonceValid: false)->process($ctx);
        } catch (PipelineException) {
            // Expected
        }

        $this->assertEmpty($this->driver->store, 'No data must be written when nonce is invalid.');
    }

    // -------------------------------------------------------------------------
    // Required field missing → pipeline exception
    // -------------------------------------------------------------------------

    public function test_missing_required_field_throws_exception(): void
    {
        $group = $this->registerGroup();

        // client_name is required but absent
        $payload = json_encode(['year' => '2024', 'status' => 'active', 'tags' => []]);
        $ctx     = new PipelineContext(1, ['fieldforge_nonce' => 'v', 'fieldforge_payload' => $payload], [$group]);

        $this->expectException(PipelineException::class);
        $this->makePipeline()->process($ctx);
    }

    // -------------------------------------------------------------------------
    // XSS via text field — sanitized, not stored raw
    // -------------------------------------------------------------------------

    public function test_xss_in_text_field_is_sanitized(): void
    {
        $group = $this->registerGroup();

        $xss     = '<script>alert("xss")</script>';
        $payload = json_encode(['client_name' => $xss, 'year' => '2024', 'status' => '', 'tags' => []]);
        $ctx     = new PipelineContext(1, ['fieldforge_nonce' => 'v', 'fieldforge_payload' => $payload], [$group]);
        $this->makePipeline()->process($ctx);

        CacheAdapter::flush();
        $stored = $this->adapter->load(1);

        $this->assertNotSame($xss, $stored['client_name'] ?? '');
        $this->assertStringNotContainsString('<script>', (string) ($stored['client_name'] ?? ''));
    }

    // -------------------------------------------------------------------------
    // Unknown keys stripped before persistence
    // -------------------------------------------------------------------------

    public function test_unknown_keys_are_stripped(): void
    {
        $group = $this->registerGroup();

        $payload = json_encode([
            'client_name'  => 'Legit',
            'hacked_field' => 'injected',
            'year'         => '2023',
            'status'       => '',
            'tags'         => [],
        ]);

        $ctx = new PipelineContext(1, ['fieldforge_nonce' => 'v', 'fieldforge_payload' => $payload], [$group]);
        $this->makePipeline()->process($ctx);

        CacheAdapter::flush();
        $stored = $this->adapter->load(1);

        $this->assertArrayNotHasKey('hacked_field', $stored ?? []);
        $this->assertArrayHasKey('client_name', $stored ?? []);
    }
}

// ---------------------------------------------------------------------------
// In-memory driver stub
// ---------------------------------------------------------------------------

class FullFlowInMemoryDriver implements PostMetaDriverInterface
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
