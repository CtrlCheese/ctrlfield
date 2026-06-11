<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields\Sanitizers;

use FieldForge\Fields\Sanitizers\ColorSanitizer;
use FieldForge\Fields\Sanitizers\DateSanitizer;
use FieldForge\Fields\Sanitizers\DateTimeSanitizer;
use FieldForge\Fields\Sanitizers\LinkSanitizer;
use FieldForge\Fields\Sanitizers\OembedSanitizer;
use FieldForge\Fields\Sanitizers\RangeSanitizer;
use FieldForge\Fields\Sanitizers\TimeSanitizer;
use PHPUnit\Framework\TestCase;

class NewSanitizersTest extends TestCase
{
    // -------------------------------------------------------------------------
    // DateSanitizer
    // -------------------------------------------------------------------------

    public function test_date_valid(): void
    {
        $this->assertSame('2026-06-09', (new DateSanitizer())->sanitize('2026-06-09'));
    }

    public function test_date_invalid_returns_null(): void
    {
        $this->assertNull((new DateSanitizer())->sanitize('not-a-date'));
        $this->assertNull((new DateSanitizer())->sanitize('09/06/2026'));
        $this->assertNull((new DateSanitizer())->sanitize('2026-13-01'));
    }

    public function test_date_empty_returns_null(): void
    {
        $this->assertNull((new DateSanitizer())->sanitize(''));
        $this->assertNull((new DateSanitizer())->sanitize(null));
    }

    // -------------------------------------------------------------------------
    // TimeSanitizer
    // -------------------------------------------------------------------------

    public function test_time_valid(): void
    {
        $this->assertSame('14:30', (new TimeSanitizer())->sanitize('14:30'));
        $this->assertSame('00:00', (new TimeSanitizer())->sanitize('00:00'));
    }

    public function test_time_invalid_returns_null(): void
    {
        $this->assertNull((new TimeSanitizer())->sanitize('25:00'));
        $this->assertNull((new TimeSanitizer())->sanitize('14:60'));
        $this->assertNull((new TimeSanitizer())->sanitize('2:30'));
        $this->assertNull((new TimeSanitizer())->sanitize('not-a-time'));
    }

    public function test_time_empty_returns_null(): void
    {
        $this->assertNull((new TimeSanitizer())->sanitize(''));
    }

    // -------------------------------------------------------------------------
    // DateTimeSanitizer
    // -------------------------------------------------------------------------

    public function test_datetime_with_seconds(): void
    {
        $this->assertSame('2026-06-09T14:30:00', (new DateTimeSanitizer())->sanitize('2026-06-09T14:30:00'));
    }

    public function test_datetime_without_seconds_normalized(): void
    {
        // Browser datetime-local input sends without seconds — normalize to full ISO 8601
        $this->assertSame('2026-06-09T14:30:00', (new DateTimeSanitizer())->sanitize('2026-06-09T14:30'));
    }

    public function test_datetime_invalid_returns_null(): void
    {
        $this->assertNull((new DateTimeSanitizer())->sanitize('not-a-datetime'));
        $this->assertNull((new DateTimeSanitizer())->sanitize('2026-06-09'));
    }

    public function test_datetime_empty_returns_null(): void
    {
        $this->assertNull((new DateTimeSanitizer())->sanitize(''));
    }

    // -------------------------------------------------------------------------
    // ColorSanitizer
    // -------------------------------------------------------------------------

    public function test_color_valid_six_digit(): void
    {
        $this->assertSame('#FF5733', (new ColorSanitizer())->sanitize('#FF5733'));
        $this->assertSame('#FF5733', (new ColorSanitizer())->sanitize('#ff5733'));
        $this->assertSame('#FF5733', (new ColorSanitizer())->sanitize('ff5733'));
    }

    public function test_color_valid_three_digit(): void
    {
        $this->assertSame('#FFF', (new ColorSanitizer())->sanitize('#fff'));
    }

    public function test_color_valid_eight_digit_alpha(): void
    {
        $this->assertSame('#FF573380', (new ColorSanitizer())->sanitize('#FF573380'));
    }

    public function test_color_invalid_returns_null(): void
    {
        $this->assertNull((new ColorSanitizer())->sanitize('red'));
        $this->assertNull((new ColorSanitizer())->sanitize('#GGGGGG'));
        $this->assertNull((new ColorSanitizer())->sanitize('#12345'));
    }

    public function test_color_empty_returns_null(): void
    {
        $this->assertNull((new ColorSanitizer())->sanitize(''));
        $this->assertNull((new ColorSanitizer())->sanitize(null));
    }

    // -------------------------------------------------------------------------
    // LinkSanitizer
    // -------------------------------------------------------------------------

    public function test_link_full_array(): void
    {
        $result = (new LinkSanitizer())->sanitize([
            'url'    => 'https://example.com',
            'title'  => 'Example',
            'target' => '_blank',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('https://example.com', $result['url']);
        $this->assertSame('Example', $result['title']);
        $this->assertSame('_blank', $result['target']);
    }

    public function test_link_target_defaults_to_self(): void
    {
        $result = (new LinkSanitizer())->sanitize([
            'url'    => 'https://example.com',
            'title'  => '',
            'target' => 'javascript:void(0)',
        ]);

        $this->assertIsArray($result);
        $this->assertSame('_self', $result['target']);
    }

    public function test_link_missing_keys_default_to_empty(): void
    {
        $result = (new LinkSanitizer())->sanitize(['url' => 'https://example.com']);

        $this->assertIsArray($result);
        $this->assertSame('', $result['title']);
        $this->assertSame('_self', $result['target']);
    }

    public function test_link_non_array_returns_null(): void
    {
        $this->assertNull((new LinkSanitizer())->sanitize('https://example.com'));
        $this->assertNull((new LinkSanitizer())->sanitize(null));
    }

    // -------------------------------------------------------------------------
    // RangeSanitizer
    // -------------------------------------------------------------------------

    public function test_range_numeric_string_cast_to_float(): void
    {
        $this->assertSame(7.5, (new RangeSanitizer())->sanitize('7.5'));
        $this->assertSame(10.0, (new RangeSanitizer())->sanitize(10));
    }

    public function test_range_non_numeric_returns_zero(): void
    {
        $this->assertSame(0.0, (new RangeSanitizer())->sanitize('abc'));
        $this->assertSame(0.0, (new RangeSanitizer())->sanitize(null));
    }

    // -------------------------------------------------------------------------
    // OembedSanitizer
    // -------------------------------------------------------------------------

    public function test_oembed_trims_string(): void
    {
        $url    = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $result = (new OembedSanitizer())->sanitize('  ' . $url . '  ');
        $this->assertSame($url, $result);
    }

    public function test_oembed_non_string_returns_empty(): void
    {
        $this->assertSame('', (new OembedSanitizer())->sanitize(null));
        $this->assertSame('', (new OembedSanitizer())->sanitize(42));
    }
}
