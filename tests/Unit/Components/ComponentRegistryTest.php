<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Components;

use FieldForge\Components\ComponentDefinition;
use FieldForge\Components\ComponentRegistry;
use PHPUnit\Framework\TestCase;

class ComponentRegistryTest extends TestCase
{
    private string $tmpBase = '';

    protected function setUp(): void
    {
        ComponentRegistry::reset();
        $this->tmpBase = sys_get_temp_dir() . '/ff_registry_test_' . uniqid();
        mkdir($this->tmpBase, 0777, true);
    }

    protected function tearDown(): void
    {
        ComponentRegistry::reset();
        $this->removeDir($this->tmpBase);
    }

    // -------------------------------------------------------------------------
    // discover()
    // -------------------------------------------------------------------------

    public function test_discover_registers_component_with_fields_file(): void
    {
        $this->makeComponent('hero', "<?php return null;");

        ComponentRegistry::discover($this->tmpBase);

        $this->assertTrue(ComponentRegistry::has('hero'));
    }

    public function test_discover_skips_directory_without_fields_file(): void
    {
        mkdir($this->tmpBase . '/empty_dir', 0777, true);

        ComponentRegistry::discover($this->tmpBase);

        $this->assertFalse(ComponentRegistry::has('empty_dir'));
    }

    public function test_discover_does_not_abort_on_broken_fields_file(): void
    {
        $this->makeComponent('good', "<?php return null;");
        $this->makeComponent('broken', "<?php throw new \RuntimeException('broken');");

        ComponentRegistry::discover($this->tmpBase);

        $this->assertTrue(ComponentRegistry::has('good'));
        $this->assertTrue(ComponentRegistry::has('broken'));
    }

    public function test_discover_key_is_lowercased_directory_name(): void
    {
        $this->makeComponent('HeroBanner', "<?php return null;");

        ComponentRegistry::discover($this->tmpBase);

        $this->assertTrue(ComponentRegistry::has('herobanner'));
    }

    public function test_discover_detects_template_blade_php(): void
    {
        $this->makeComponent('cta', "<?php return null;");
        file_put_contents($this->tmpBase . '/cta/index.blade.php', '<div>CTA</div>');

        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get('cta');
        $this->assertStringEndsWith('index.blade.php', (string) $def?->templatePath);
    }

    public function test_discover_detects_template_php_fallback(): void
    {
        $this->makeComponent('card', "<?php return null;");
        file_put_contents($this->tmpBase . '/card/index.php', '<div>Card</div>');

        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get('card');
        $this->assertStringEndsWith('index.php', (string) $def?->templatePath);
    }

    public function test_discover_prefers_blade_over_php(): void
    {
        $this->makeComponent('feature', "<?php return null;");
        file_put_contents($this->tmpBase . '/feature/index.blade.php', '<div>Blade</div>');
        file_put_contents($this->tmpBase . '/feature/index.php', '<div>PHP</div>');

        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get('feature');
        $this->assertStringEndsWith('index.blade.php', (string) $def?->templatePath);
    }

    public function test_discover_template_path_null_when_no_template(): void
    {
        $this->makeComponent('bare', "<?php return null;");

        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get('bare');
        $this->assertNull($def?->templatePath);
    }

    public function test_discover_detects_script_js(): void
    {
        $this->makeComponent('widget', "<?php return null;");
        file_put_contents($this->tmpBase . '/widget/script.js', 'console.log("widget")');

        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get('widget');
        $this->assertTrue($def?->hasScript);
    }

    public function test_discover_autoloads_functions_php(): void
    {
        $this->makeComponent('loader', "<?php return null;");
        file_put_contents(
            $this->tmpBase . '/loader/functions.php',
            "<?php \$GLOBALS['_ff_loader_functions_loaded'] = true;"
        );

        ComponentRegistry::discover($this->tmpBase);

        $this->assertTrue($GLOBALS['_ff_loader_functions_loaded'] ?? false);
    }

    public function test_all_returns_all_registered(): void
    {
        $this->makeComponent('hero', "<?php return null;");
        $this->makeComponent('footer', "<?php return null;");

        ComponentRegistry::discover($this->tmpBase);

        $all = ComponentRegistry::all();
        $this->assertArrayHasKey('hero', $all);
        $this->assertArrayHasKey('footer', $all);
        $this->assertCount(2, $all);
    }

    public function test_reset_clears_all_components(): void
    {
        $this->makeComponent('hero', "<?php return null;");
        ComponentRegistry::discover($this->tmpBase);
        $this->assertTrue(ComponentRegistry::has('hero'));

        ComponentRegistry::reset();

        $this->assertFalse(ComponentRegistry::has('hero'));
        $this->assertEmpty(ComponentRegistry::all());
    }

    public function test_get_returns_null_for_unknown_key(): void
    {
        $this->assertNull(ComponentRegistry::get('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // labelFromKey() — tested via discover() label derivation
    // -------------------------------------------------------------------------

    /** @dataProvider labelFromKeyProvider */
    public function test_label_derived_from_key(string $dirName, string $expectedLabel): void
    {
        $this->makeComponent($dirName, "<?php return null;");
        ComponentRegistry::discover($this->tmpBase);

        $def = ComponentRegistry::get(strtolower($dirName));
        $this->assertSame($expectedLabel, $def?->label);
    }

    /** @return array<string, array{string, string}> */
    public static function labelFromKeyProvider(): array
    {
        return [
            'snake_case'  => ['hero_section', 'Hero Section'],
            'kebab-case'  => ['hero-banner', 'Hero Banner'],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function makeComponent(string $name, string $fieldsPhpContent): void
    {
        $dir = $this->tmpBase . '/' . $name;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/fields.php', $fieldsPhpContent);
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }

        rmdir($path);
    }
}
