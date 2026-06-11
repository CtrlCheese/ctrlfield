<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Blade;

use FieldForge\Bootstrap\ServiceContainer;
use FieldForge\Integrations\Blade\ViewServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class ViewServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static compiler before each test
        $ref = new \ReflectionClass(ViewServiceProvider::class);
        $prop = $ref->getProperty('compiler');
        $prop->setValue(null, null);
    }

    public function test_boot_registers_field_directive(): void
    {
        $provider = new ViewServiceProvider(new ServiceContainer());
        $provider->boot();

        $compiler = ViewServiceProvider::compiler();

        $this->assertInstanceOf(BladeCompiler::class, $compiler);

        $customDirectives = $compiler->getCustomDirectives();
        $this->assertArrayHasKey('field',      $customDirectives);
        $this->assertArrayHasKey('field_raw',  $customDirectives);
        $this->assertArrayHasKey('repeater',   $customDirectives);
        $this->assertArrayHasKey('endrepeater', $customDirectives);
    }

    public function test_field_directive_compiles_to_escaped_output(): void
    {
        $provider = new ViewServiceProvider(new ServiceContainer());
        $provider->boot();

        $compiler = ViewServiceProvider::compiler();
        $this->assertNotNull($compiler);

        $compiled = $compiler->compileString("@field('client_name')");

        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString("fieldforge_get('client_name')", $compiled);
    }

    public function test_field_raw_directive_compiles_without_escaping(): void
    {
        $provider = new ViewServiceProvider(new ServiceContainer());
        $provider->boot();

        $compiler = ViewServiceProvider::compiler();
        $this->assertNotNull($compiler);

        $compiled = $compiler->compileString("@field_raw('bio')");

        $this->assertStringNotContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString("fieldforge_get('bio')", $compiled);
    }

    public function test_repeater_directive_compiles_to_foreach(): void
    {
        $provider = new ViewServiceProvider(new ServiceContainer());
        $provider->boot();

        $compiler = ViewServiceProvider::compiler();
        $this->assertNotNull($compiler);

        $compiled = $compiler->compileString(
            "@repeater('schedule')<li>row</li>@endrepeater"
        );

        $this->assertStringContainsString('foreach', $compiled);
        $this->assertStringContainsString('extract', $compiled);
        $this->assertStringContainsString('unset', $compiled);
    }

    public function test_compiler_is_null_before_boot(): void
    {
        $this->assertNull(ViewServiceProvider::compiler());
    }
}
