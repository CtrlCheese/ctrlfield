<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Core\Pipeline\Stages;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Core\Pipeline\PipelineContext;
use CtrlField\Core\Pipeline\Stages\SanitizationStage;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use CtrlField\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

class SanitizationStageTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
        CacheAdapter::flush();
    }

    private function makeAdapter(array $existing = []): PostMetaAdapter
    {
        $driver = new SanitizationInMemoryDriver();

        if (! empty($existing)) {
            $adapter = new PostMetaAdapter($driver);
            $adapter->save(1, $existing, 1);
            CacheAdapter::flush(); // ensure stage loads fresh from driver
        }

        return new PostMetaAdapter($driver);
    }

    private function makeGroup(array $fieldDefs): FieldGroup
    {
        return FieldGroup::make('g')->where('post_type', '==', 'p')->fields($fieldDefs);
    }

    // -------------------------------------------------------------------------
    // Basic sanitization
    // -------------------------------------------------------------------------

    public function test_sanitizes_text_field(): void
    {
        $group   = $this->makeGroup([Field::text('name')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['name' => '<script>alert(1)</script>Hello'];

        $stage->handle($context);

        $this->assertStringNotContainsString('<script>', $context->fields['name']);
        $this->assertStringContainsString('Hello', $context->fields['name']);
    }

    public function test_sanitizes_number_field_preserves_value(): void
    {
        $group   = $this->makeGroup([Field::number('score')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['score' => 42];

        $stage->handle($context);

        $this->assertSame(42, $context->fields['score']);
    }

    // -------------------------------------------------------------------------
    // visible_when preservation (critical acceptance criterion)
    // -------------------------------------------------------------------------

    public function test_preserves_hidden_field_value(): void
    {
        // Existing stored data has 'internal_notes' (a hidden field not in current payload)
        $existing = ['client_name' => 'Acme', 'internal_notes' => 'Secret'];

        $group   = $this->makeGroup([
            Field::text('client_name'),
            Field::text('internal_notes')->visibleWhen('type', '==', 'internal'),
        ]);
        $adapter = $this->makeAdapter($existing);
        $stage   = new SanitizationStage($adapter);

        // Only 'client_name' submitted — 'internal_notes' is hidden/absent
        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['client_name' => 'Acme Updated'];

        $stage->handle($context);

        // Hidden field must be preserved
        $this->assertSame('Secret', $context->fields['internal_notes']);
        $this->assertSame('Acme Updated', $context->fields['client_name']);
    }

    public function test_submitted_value_overwrites_existing(): void
    {
        $existing = ['client_name' => 'Old Name'];
        $group    = $this->makeGroup([Field::text('client_name')]);
        $adapter  = $this->makeAdapter($existing);
        $stage    = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['client_name' => 'New Name'];

        $stage->handle($context);

        $this->assertSame('New Name', $context->fields['client_name']);
    }

    public function test_no_existing_data_does_not_throw(): void
    {
        $group   = $this->makeGroup([Field::text('name')]);
        $adapter = $this->makeAdapter(); // empty store
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['name' => 'Hello'];

        $stage->handle($context);

        $this->assertSame('Hello', $context->fields['name']);
    }

    // -------------------------------------------------------------------------
    // Indexed fields extraction
    // -------------------------------------------------------------------------

    public function test_extracts_indexed_fields_into_context(): void
    {
        $group   = $this->makeGroup([
            Field::text('client_name')->setIndex(true),
            Field::text('internal_ref'),
        ]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['client_name' => 'Acme', 'internal_ref' => 'REF-001'];

        $stage->handle($context);

        $this->assertArrayHasKey('client_name', $context->indexedFields);
        $this->assertArrayNotHasKey('internal_ref', $context->indexedFields);
        $this->assertSame('Acme', $context->indexedFields['client_name']);
    }

    public function test_non_indexed_fields_not_in_indexed_context(): void
    {
        $group   = $this->makeGroup([Field::text('name')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['name' => 'Acme'];

        $stage->handle($context);

        $this->assertEmpty($context->indexedFields);
    }

    // -------------------------------------------------------------------------
    // CYCLES4 new field type sanitization
    // -------------------------------------------------------------------------

    public function test_true_false_cast_to_int(): void
    {
        $group   = $this->makeGroup([Field::trueFalse('active')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['active' => '1'];
        $stage->handle($context);
        $this->assertSame(1, $context->fields['active']);

        $context->fields = ['active' => ''];
        $stage->handle($context);
        $this->assertSame(0, $context->fields['active']);
    }

    public function test_button_group_sanitized_as_text(): void
    {
        $group   = $this->makeGroup([Field::buttonGroup('size')->options(['sm' => 'Small'])]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['size' => '<b>large</b>'];
        $stage->handle($context);

        $this->assertStringNotContainsString('<b>', $context->fields['size']);
    }

    public function test_user_single_cast_to_int(): void
    {
        $group   = $this->makeGroup([Field::user('author')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['author' => '42'];
        $stage->handle($context);

        $this->assertSame(42, $context->fields['author']);
    }

    public function test_user_multiple_returns_array_of_ints(): void
    {
        $group   = $this->makeGroup([Field::user('contributors')->multiple()]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['contributors' => ['3', '7', '12']];
        $stage->handle($context);

        $this->assertSame([3, 7, 12], $context->fields['contributors']);
    }

    public function test_icon_sanitized_as_text(): void
    {
        $group   = $this->makeGroup([Field::icon('social_icon')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = ['social_icon' => 'dashicons-admin-home'];
        $stage->handle($context);

        $this->assertSame('dashicons-admin-home', $context->fields['social_icon']);
    }

    public function test_code_field_strips_xss_via_kses(): void
    {
        $group   = $this->makeGroup([Field::code('snippet')]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        // <script> is not in the kses allowed list
        $context->fields = ['snippet' => '<?php echo "hello"; ?><script>alert(1)</script>'];
        $stage->handle($context);

        $this->assertStringNotContainsString('<script>', (string) $context->fields['snippet']);
    }

    public function test_tab_separator_message_accordion_are_passthrough(): void
    {
        $group   = $this->makeGroup([
            Field::tab('details'),
            Field::separator('div'),
            Field::message('notice'),
            Field::accordion('advanced'),
        ]);
        $adapter = $this->makeAdapter();
        $stage   = new SanitizationStage($adapter);

        $context         = new PipelineContext(1, [], [$group]);
        $context->fields = [
            'details'  => 'raw',
            'div'      => 'raw',
            'notice'   => 'raw',
            'advanced' => 'raw',
        ];
        $stage->handle($context);

        // UI-only fields never appear in real payloads, but if they do, they pass through
        $this->assertSame('raw', $context->fields['details']);
        $this->assertSame('raw', $context->fields['div']);
    }
}

// ---------------------------------------------------------------------------
// In-memory driver reused from PostMetaAdapterTest (local stub)
// ---------------------------------------------------------------------------

class SanitizationInMemoryDriver implements PostMetaDriverInterface
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
