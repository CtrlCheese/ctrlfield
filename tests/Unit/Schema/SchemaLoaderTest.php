<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Schema;

use FieldForge\Registry\FieldRegistry;
use FieldForge\Schema\Exceptions\SchemaDirectoryNotFoundException;
use FieldForge\Schema\SchemaLoader;
use PHPUnit\Framework\TestCase;

class SchemaLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/fieldforge_schema_' . uniqid();
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

        file_put_contents($this->tmpDir . '/b_schema.php', '<?php $GLOBALS["fieldforge_load_order"][] = "b";');
        file_put_contents($this->tmpDir . '/a_schema.php', '<?php $GLOBALS["fieldforge_load_order"][] = "a";');
        file_put_contents($this->tmpDir . '/c_schema.php', '<?php $GLOBALS["fieldforge_load_order"][] = "c";');

        $GLOBALS['fieldforge_load_order'] = [];
        SchemaLoader::loadDirectory($this->tmpDir);

        self::assertSame(['a', 'b', 'c'], $GLOBALS['fieldforge_load_order']);

        unset($GLOBALS['fieldforge_load_order']);
    }

    public function testLoadDirectoryIgnoresNonPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/notes.txt', 'not php');
        file_put_contents($this->tmpDir . '/schema.php', '<?php $GLOBALS["fieldforge_txt_ignored"] = true;');

        $GLOBALS['fieldforge_txt_ignored'] = false;
        SchemaLoader::loadDirectory($this->tmpDir);

        self::assertTrue($GLOBALS['fieldforge_txt_ignored']);
        unset($GLOBALS['fieldforge_txt_ignored']);
    }

    public function testLoadFileExecutesPhpFile(): void
    {
        $path = $this->tmpDir . '/test_file.php';
        file_put_contents($path, '<?php $GLOBALS["fieldforge_file_loaded"] = true;');

        $GLOBALS['fieldforge_file_loaded'] = false;
        SchemaLoader::loadFile($path);

        self::assertTrue($GLOBALS['fieldforge_file_loaded']);
        unset($GLOBALS['fieldforge_file_loaded']);
    }

    public function testLoadDirectoryWithEmptyDirectoryDoesNotThrow(): void
    {
        SchemaLoader::loadDirectory($this->tmpDir);
        $this->addToAssertionCount(1);
    }
}
