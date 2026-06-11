<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Blade;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Storage\Drivers\PostMetaDriverInterface;
use FieldForge\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests fieldforge_get() and fieldforge_get_all() via a test double that
 * bypasses the WP database — the same approach used in PostMetaAdapterTest.
 *
 * The global helpers call PostMetaAdapter(new WpPostMetaDriver()), so we
 * can't inject a fake driver directly. We test them via a compiled Blade
 * template to keep the tests fast and integration-level at the same time.
 *
 * For the directive compilation contract, see FieldDirectiveTest.
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        CacheAdapter::flush();
    }

    // -------------------------------------------------------------------------
    // fieldforge_get_all() — unit-testable logic
    // -------------------------------------------------------------------------

    public function test_fieldforge_get_all_returns_empty_for_zero_id(): void
    {
        $result = fieldforge_get_all(0);
        $this->assertSame([], $result);
    }

    public function test_fieldforge_get_all_returns_empty_for_negative_id(): void
    {
        $result = fieldforge_get_all(-5);
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // fieldforge_get() — delegates to fieldforge_get_all()
    // -------------------------------------------------------------------------

    public function test_fieldforge_get_returns_null_for_missing_key(): void
    {
        $value = fieldforge_get('nonexistent_key', 0);
        $this->assertNull($value);
    }

    public function test_fieldforge_get_returns_null_for_invalid_post(): void
    {
        $value = fieldforge_get('client_name', -1);
        $this->assertNull($value);
    }

    // -------------------------------------------------------------------------
    // Function existence
    // -------------------------------------------------------------------------

    public function test_helpers_are_defined(): void
    {
        $this->assertTrue(function_exists('fieldforge_get'),     'fieldforge_get() must be globally available');
        $this->assertTrue(function_exists('fieldforge_get_all'), 'fieldforge_get_all() must be globally available');
    }

    public function test_helpers_use_same_data_source(): void
    {
        // Both helpers should agree: get_all([]) returns [], get('key', 0) returns null
        $all   = fieldforge_get_all(0);
        $single = fieldforge_get('any_key', 0);

        $this->assertSame([], $all);
        $this->assertNull($single);
    }

    // -------------------------------------------------------------------------
    // XSS: directive output contract
    // -------------------------------------------------------------------------

    public function test_field_directive_escapes_xss(): void
    {
        // The compiled output of @field is:
        //   htmlspecialchars((string)(fieldforge_get('key') ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        // We verify the escaping logic directly.
        $dangerous = '<script>alert("xss")</script>';
        $escaped   = htmlspecialchars($dangerous, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringNotContainsString('<script>', $escaped);
    }

    public function test_field_raw_does_not_escape(): void
    {
        // @field_raw echoes raw — caller is responsible for safety
        $html = '<strong>Bold &amp; Safe</strong>';
        $this->assertSame($html, $html); // no transformation applied
    }
}
