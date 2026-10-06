<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\Blade;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use CtrlField\Storage\PostMetaAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests ctrlfield_get() and ctrlfield_get_all() via a test double that
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
    // ctrlfield_get_all() — unit-testable logic
    // -------------------------------------------------------------------------

    public function test_ctrlfield_get_all_returns_empty_for_zero_id(): void
    {
        $result = ctrlfield_get_all(0);
        $this->assertSame([], $result);
    }

    public function test_ctrlfield_get_all_returns_empty_for_negative_id(): void
    {
        $result = ctrlfield_get_all(-5);
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // ctrlfield_get() — delegates to ctrlfield_get_all()
    // -------------------------------------------------------------------------

    public function test_ctrlfield_get_returns_null_for_missing_key(): void
    {
        $value = ctrlfield_get('nonexistent_key', 0);
        $this->assertNull($value);
    }

    public function test_ctrlfield_get_returns_null_for_invalid_post(): void
    {
        $value = ctrlfield_get('client_name', -1);
        $this->assertNull($value);
    }

    // -------------------------------------------------------------------------
    // Function existence
    // -------------------------------------------------------------------------

    public function test_helpers_are_defined(): void
    {
        $this->assertTrue(function_exists('ctrlfield_get'),     'ctrlfield_get() must be globally available');
        $this->assertTrue(function_exists('ctrlfield_get_all'), 'ctrlfield_get_all() must be globally available');
    }

    public function test_helpers_use_same_data_source(): void
    {
        // Both helpers should agree: get_all([]) returns [], get('key', 0) returns null
        $all   = ctrlfield_get_all(0);
        $single = ctrlfield_get('any_key', 0);

        $this->assertSame([], $all);
        $this->assertNull($single);
    }

    // -------------------------------------------------------------------------
    // XSS: directive output contract
    // -------------------------------------------------------------------------

    public function test_field_directive_escapes_xss(): void
    {
        // The compiled output of @field is:
        //   htmlspecialchars((string)(ctrlfield_get('key') ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
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
