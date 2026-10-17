<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\FieldGroups;

use CtrlField\Admin\FieldGroups\PhpFileWriter;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

final class PhpFileWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ctrlf-php-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/{,.}*.{php,tmp}', GLOB_BRACE) ?: []);
        @rmdir($this->dir);
    }

    public function test_writes_key_php_in_the_folder(): void
    {
        $path = (new PhpFileWriter($this->dir))->write('hero', "<?php\n\nreturn 1;\n");

        $this->assertSame($this->dir . '/hero.php', $path);
        $this->assertSame("<?php\n" . PhpFileWriter::MARKER . "\n\nreturn 1;\n", file_get_contents($path));
        $this->assertTrue(PhpFileWriter::isOurs($path));
        $this->assertSame([], glob($this->dir . '/.*.tmp'), 'no temp file left');
    }

    public function test_never_replaces_a_file_unless_asked(): void
    {
        $writer = new PhpFileWriter($this->dir);
        file_put_contents($this->dir . '/hero.php', "<?php // by hand\n");

        try {
            $writer->write('hero', "<?php\n");
            $this->fail('expected exists');
        } catch (\RuntimeException $e) {
            $this->assertSame(PhpFileWriter::ERR_EXISTS, $e->getMessage());
        }
        $this->assertSame("<?php // by hand\n", file_get_contents($this->dir . '/hero.php'));

        try {
            $writer->write('hero', "<?php\n", overwrite: true);
            $this->fail('a file a developer wrote is never replaced');
        } catch (\RuntimeException $e) {
            $this->assertSame(PhpFileWriter::ERR_NOT_OURS, $e->getMessage());
        }
        $this->assertSame("<?php // by hand\n", file_get_contents($this->dir . '/hero.php'));

        unlink($this->dir . '/hero.php');
        $writer->write('hero', "<?php\nreturn 1;\n");
        $writer->write('hero', "<?php\nreturn 2;\n", overwrite: true);
        $this->assertStringEndsWith("return 2;\n", (string) file_get_contents($this->dir . '/hero.php'), 'its own file is replaced');
    }

    public function test_refuses_bad_keys_and_code_that_does_not_parse(): void
    {
        $writer = new PhpFileWriter($this->dir);

        foreach (['../evil', 'a/b', 'Hero', ''] as $key) {
            $this->assertNull($writer->path($key), $key);
        }
        $this->expectExceptionMessage(PhpFileWriter::ERR_INVALID);
        $writer->write('hero', "<?php function (\n");
    }

    public function test_no_folder_is_reported(): void
    {
        $this->assertSame(PhpFileWriter::ERR_NOT_WRITABLE, (new PhpFileWriter('/nonexistent-root-dir/x'))->problem());
    }

    public function test_generated_group_code_parses(): void
    {
        [$group] = JsonGroup::normalize([
            'key'      => 'hero',
            'title'    => "It's \\ a 'test'",
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]],
            'fields'   => [['key' => 'title', 'type' => 'text', 'label' => "Line 1\nLine '2'"]],
        ]);

        $this->assertTrue(PhpFileWriter::parses(JsonGroup::toPhp($group)));
    }
}
