<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Pipeline\Stages;

use FieldForge\Core\Pipeline\PipelineContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\Stages\TypeCoercionStage;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class TypeCoercionNewTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    private function coerce(array $fields, array $values): array
    {
        $group = Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields($fields);

        $ctx         = new PipelineContext(1, [], [$group]);
        $ctx->fields = $values;

        (new TypeCoercionStage())->handle($ctx);

        return $ctx->fields;
    }

    // -------------------------------------------------------------------------
    // DateField
    // -------------------------------------------------------------------------

    public function test_valid_date_passes_through(): void
    {
        $result = $this->coerce([Field::date('d')], ['d' => '2026-06-09']);
        $this->assertSame('2026-06-09', $result['d']);
    }

    public function test_empty_date_passes_through(): void
    {
        $result = $this->coerce([Field::date('d')], ['d' => '']);
        $this->assertSame('', $result['d']);
    }

    public function test_invalid_date_throws_coercion_failed(): void
    {
        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage("Field 'd' expects ISO 8601 date");

        $this->coerce([Field::date('d')], ['d' => 'not-a-date']);
    }

    public function test_date_wrong_format_throws(): void
    {
        $this->expectException(PipelineException::class);
        $this->coerce([Field::date('d')], ['d' => '09/06/2026']);
    }

    public function test_coercion_failed_error_code(): void
    {
        try {
            $this->coerce([Field::date('d')], ['d' => 'bad']);
            $this->fail('Expected PipelineException');
        } catch (PipelineException $e) {
            $this->assertSame('COERCION_FAILED', $e->errorCode);
            $this->assertSame('d', $e->fieldKey);
            $this->assertSame(TypeCoercionStage::NAME, $e->stageName);
        }
    }

    // -------------------------------------------------------------------------
    // TimeField
    // -------------------------------------------------------------------------

    public function test_valid_time_passes_through(): void
    {
        $result = $this->coerce([Field::time('t')], ['t' => '14:30']);
        $this->assertSame('14:30', $result['t']);
    }

    public function test_invalid_time_throws(): void
    {
        $this->expectException(PipelineException::class);
        $this->coerce([Field::time('t')], ['t' => '2:30']);
    }

    // -------------------------------------------------------------------------
    // DateTimeField
    // -------------------------------------------------------------------------

    public function test_datetime_with_seconds_passes_through(): void
    {
        $result = $this->coerce([Field::datetime('dt')], ['dt' => '2026-06-09T14:30:00']);
        $this->assertSame('2026-06-09T14:30:00', $result['dt']);
    }

    public function test_datetime_without_seconds_normalized(): void
    {
        $result = $this->coerce([Field::datetime('dt')], ['dt' => '2026-06-09T14:30']);
        $this->assertSame('2026-06-09T14:30:00', $result['dt']);
    }

    public function test_invalid_datetime_throws(): void
    {
        $this->expectException(PipelineException::class);
        $this->coerce([Field::datetime('dt')], ['dt' => '2026-06-09']);
    }

    // -------------------------------------------------------------------------
    // ColorField
    // -------------------------------------------------------------------------

    public function test_color_lowercased_hex_normalized(): void
    {
        $result = $this->coerce([Field::color('c')], ['c' => '#ff5733']);
        $this->assertSame('#FF5733', $result['c']);
    }

    public function test_color_without_hash_prefix(): void
    {
        $result = $this->coerce([Field::color('c')], ['c' => 'ff5733']);
        $this->assertSame('#FF5733', $result['c']);
    }

    public function test_color_empty_passes_through(): void
    {
        $result = $this->coerce([Field::color('c')], ['c' => '']);
        $this->assertSame('', $result['c']);
    }

    // -------------------------------------------------------------------------
    // LinkField
    // -------------------------------------------------------------------------

    public function test_link_array_coerced(): void
    {
        $result = $this->coerce(
            [Field::link('cta')],
            ['cta' => ['url' => 'https://example.com', 'title' => 'Click', 'target' => '_blank']],
        );

        $this->assertSame('https://example.com', $result['cta']['url']);
        $this->assertSame('Click', $result['cta']['title']);
        $this->assertSame('_blank', $result['cta']['target']);
    }

    public function test_link_invalid_target_defaults_to_self(): void
    {
        $result = $this->coerce(
            [Field::link('cta')],
            ['cta' => ['url' => 'https://example.com', 'title' => '', 'target' => 'evil']],
        );
        $this->assertSame('_self', $result['cta']['target']);
    }

    public function test_link_missing_keys_filled(): void
    {
        $result = $this->coerce([Field::link('cta')], ['cta' => []]);
        $this->assertSame('', $result['cta']['url']);
        $this->assertSame('', $result['cta']['title']);
        $this->assertSame('_self', $result['cta']['target']);
    }

    public function test_link_non_array_becomes_empty_link(): void
    {
        $result = $this->coerce([Field::link('cta')], ['cta' => 'https://example.com']);
        $this->assertSame('', $result['cta']['url']);
    }

    // -------------------------------------------------------------------------
    // RangeField
    // -------------------------------------------------------------------------

    public function test_range_clamped_to_max(): void
    {
        $result = $this->coerce(
            [Field::range('score')->min(1.0)->max(10.0)],
            ['score' => '15'],
        );
        $this->assertSame(10.0, $result['score']);
    }

    public function test_range_clamped_to_min(): void
    {
        $result = $this->coerce(
            [Field::range('score')->min(1.0)->max(10.0)],
            ['score' => '-5'],
        );
        $this->assertSame(1.0, $result['score']);
    }

    public function test_range_valid_value_passes_through(): void
    {
        $result = $this->coerce(
            [Field::range('score')->min(0.0)->max(100.0)->step(0.5)],
            ['score' => '7.3'],
        );
        $this->assertSame(7.3, $result['score']);
    }

    public function test_range_non_numeric_becomes_min(): void
    {
        $result = $this->coerce(
            [Field::range('score')->min(1.0)->max(10.0)],
            ['score' => 'abc'],
        );
        // 0.0 coerced, then clamped to min(1.0)
        $this->assertSame(1.0, $result['score']);
    }

    // -------------------------------------------------------------------------
    // OembedField
    // -------------------------------------------------------------------------

    public function test_oembed_string_trimmed(): void
    {
        $url    = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $result = $this->coerce([Field::oembed('video')], ['video' => '  ' . $url . '  ']);
        $this->assertSame($url, $result['video']);
    }

    public function test_oembed_non_string_cast(): void
    {
        $result = $this->coerce([Field::oembed('video')], ['video' => 42]);
        $this->assertSame('42', $result['video']);
    }
}
