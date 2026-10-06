<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Schema;

use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\Exceptions\SchemaDirectoryNotFoundException;
use CtrlField\Schema\SchemaLoader;
use PHPUnit\Framework\TestCase;

class SchemaLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ctrlfield_schema_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
        FieldRegistry::reset();
    }

    public function testLoadDirectoryThrowsIfPathDoesNotExist(): void
    {
        $this->expectException(SchemaDirectoryNotFoundException::class);
        SchemaLoader::loadDirectory('/nonexistent/path/xyz');
    }

    public function testLoadDirectoryLoadsPhpFilesAlphabetically(): void
    {
        $order = [];

        file_put_contents($this->tmpDir . '/b_schema.php', '<?php $GLOBALS["ctrlfield_load_order"][] = "b";');
        file_put_contents($this->tmpDir . '/a_schema.php', '<?php $GLOBALS["ctrlfield_load_order"][] = "a";');
        file_put_contents($this->tmpDir . '/c_schema.php', '<?php $GLOBALS["ctrlfield_load_order"][] = "c";');

        $GLOBALS['ctrlfield_load_order'] = [];
        SchemaLoader::loadDirectory($this->tmpDir);

        self::assertSame(['a', 'b', 'c'], $GLOBALS['ctrlfield_load_order']);

        unset($GLOBALS['ctrlfield_load_order']);
    }

    public function testLoadDirectoryIgnoresNonPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/notes.txt', 'not php');
        file_put_contents($this->tmpDir . '/schema.php', '<?php $GLOBALS["ctrlfield_txt_ignored"] = true;');

        $GLOBALS['ctrlfield_txt_ignored'] = false;
        SchemaLoader::loadDirectory($this->tmpDir);

        self::assertTrue($GLOBALS['ctrlfield_txt_ignored']);
        unset($GLOBALS['ctrlfield_txt_ignored']);
    }

    public function testLoadFileExecutesPhpFile(): void
    {
        $path = $this->tmpDir . '/test_file.php';
        file_put_contents($path, '<?php $GLOBALS["ctrlfield_file_loaded"] = true;');

        $GLOBALS['ctrlfield_file_loaded'] = false;
        SchemaLoader::loadFile($path);

        self::assertTrue($GLOBALS['ctrlfield_file_loaded']);
        unset($GLOBALS['ctrlfield_file_loaded']);
    }

    public function testLoadDirectoryWithEmptyDirectoryDoesNotThrow(): void
    {
        SchemaLoader::loadDirectory($this->tmpDir);
        $this->addToAssertionCount(1);
    }

    public function testBrokenSchemaFileIsSkippedAndOtherFilesStillLoad(): void
    {
        \CtrlField\Schema\SchemaErrors::reset();
        $GLOBALS['ctrlfield_load_order'] = [];
        file_put_contents($this->tmpDir . '/a_ok.php', '<?php $GLOBALS["ctrlfield_load_order"][] = "a";');
        file_put_contents($this->tmpDir . '/b_broken.php', '<?php throw new InvalidArgumentException("Invalid field width 40");');
        file_put_contents($this->tmpDir . '/c_ok.php', '<?php $GLOBALS["ctrlfield_load_order"][] = "c";');

        SchemaLoader::loadDirectory($this->tmpDir);

        $this->assertSame(['a', 'c'], $GLOBALS['ctrlfield_load_order']);
        $errors = \CtrlField\Schema\SchemaErrors::all();
        $this->assertCount(1, $errors);
        $this->assertStringEndsWith('b_broken.php', array_key_first($errors));
        $this->assertSame('Invalid field width 40', reset($errors));
    }
}
