<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Components;

use CtrlField\Components\ComponentRegistry;
use CtrlField\Schema\SchemaErrors;
use PHPUnit\Framework\TestCase;

/** discover() options asked for by themes: 'load', 'scripts', broken fields files. */
final class ComponentOptionsTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        ComponentRegistry::reset();
        SchemaErrors::reset();
        $this->base = sys_get_temp_dir() . '/ctrlf_opts_' . uniqid();
        mkdir($this->base . '/ModuleCta', 0777, true);
        file_put_contents($this->base . '/ModuleCta/fields.php', '<?php return [$GLOBALS["_ctrlf_context"] ?? "none"];');
        file_put_contents($this->base . '/ModuleCta/script.js', '');
    }

    protected function tearDown(): void
    {
        ComponentRegistry::reset();
        SchemaErrors::reset();
        unset($GLOBALS['_ctrlf_context']);
        array_map('unlink', glob($this->base . '/*/*') ?: []);
        array_map('rmdir', glob($this->base . '/*') ?: []);
        @rmdir($this->base);
    }

    public function test_load_runs_the_fields_file_in_the_themes_context_once(): void
    {
        $calls = 0;
        ComponentRegistry::discover($this->base, 'fields.php', [
            'key'  => 'snake',
            'load' => static function (string $file, string $key) use (&$calls): mixed {
                $calls++;
                $GLOBALS['_ctrlf_context'] = $key; // e.g. the theme's ComponentFields::forComponent()
                try {
                    return require $file;
                } finally {
                    unset($GLOBALS['_ctrlf_context']);
                }
            },
        ]);

        $this->assertSame(1, $calls);
        $this->assertSame(['module_cta'], ComponentRegistry::loadedFields('module_cta'));
    }

    public function test_scripts_can_be_left_to_the_theme(): void
    {
        ComponentRegistry::discover($this->base);
        $this->assertTrue(ComponentRegistry::get('modulecta')?->hasScript);

        ComponentRegistry::reset();
        ComponentRegistry::discover($this->base, 'fields.php', ['scripts' => false]);
        $this->assertFalse(ComponentRegistry::get('modulecta')?->hasScript);
    }

    public function test_a_broken_fields_file_is_reported(): void
    {
        file_put_contents($this->base . '/ModuleCta/fields.php', '<?php throw new \RuntimeException("Undefined helper F::k");');

        ComponentRegistry::discover($this->base);

        $this->assertTrue(ComponentRegistry::has('modulecta'));
        $this->assertSame(['Undefined helper F::k'], array_values(SchemaErrors::all()));
    }
}
