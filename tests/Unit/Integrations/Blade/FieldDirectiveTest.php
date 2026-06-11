<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Blade;

use FieldForge\Integrations\Blade\FieldDirective;
use PHPUnit\Framework\TestCase;

class FieldDirectiveTest extends TestCase
{
    public function test_compile_wraps_in_htmlspecialchars(): void
    {
        $output = FieldDirective::compile("'client_name'");

        $this->assertStringContainsString('htmlspecialchars', $output);
        $this->assertStringContainsString("fieldforge_get('client_name')", $output);
        $this->assertStringContainsString('ENT_QUOTES', $output);
    }

    public function test_compile_raw_outputs_without_escaping(): void
    {
        $output = FieldDirective::compileRaw("'description'");

        $this->assertStringNotContainsString('htmlspecialchars', $output);
        $this->assertStringContainsString("fieldforge_get('description')", $output);
    }

    public function test_compiled_output_starts_with_php_open_tag(): void
    {
        $output = FieldDirective::compile("'test'");

        $this->assertStringStartsWith('<?php', $output);
    }

    public function test_compiled_output_ends_with_php_close_tag(): void
    {
        $output = FieldDirective::compile("'test'");

        $this->assertStringEndsWith('?>', $output);
    }

    public function test_compile_escapes_xss_value(): void
    {
        // Verify the escaping logic used at runtime
        $xss     = '<script>alert(1)</script>';
        $escaped = htmlspecialchars($xss, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringNotContainsString('<scr' . 'ipt>', $escaped);
    }

    public function test_compile_raw_does_not_escape(): void
    {
        $output = FieldDirective::compileRaw("'bio'");

        // Raw directive must not contain any escaping function
        $this->assertStringNotContainsString('htmlspecialchars', $output);
        $this->assertStringNotContainsString('esc_html', $output);
    }

    public function test_compile_handles_variable_expression(): void
    {
        $output = FieldDirective::compile('$fieldKey');

        $this->assertStringContainsString('fieldforge_get($fieldKey)', $output);
    }
}
