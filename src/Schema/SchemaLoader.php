<?php

declare(strict_types=1);

namespace CtrlField\Schema;

use CtrlField\Schema\Exceptions\SchemaDirectoryNotFoundException;

final class SchemaLoader
{
    /**
     * Load all *.php files in a directory as CtrlField schema definitions.
     * Files are loaded in alphabetical order.
     *
     * @throws SchemaDirectoryNotFoundException if the path does not exist
     */
    public static function loadDirectory(string $absolutePath): void
    {
        if (! is_dir($absolutePath)) {
            throw new SchemaDirectoryNotFoundException(
                "CtrlField schema directory not found: {$absolutePath}"
            );
        }

        $files = glob(rtrim($absolutePath, '/\\') . '/*.php');

        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            self::loadFileSafely($file);
        }
    }

    /**
     * Load a single schema file. Throws whatever the file throws — used by
     * `wp ctrlfield validate`, which should fail loudly.
     */
    public static function loadFile(string $absolutePath): void
    {
        require_once $absolutePath;
    }

    /**
     * Load a schema file without letting its errors escape: a broken file is
     * skipped and recorded in SchemaErrors; the other files still load.
     */
    public static function loadFileSafely(string $absolutePath): bool
    {
        try {
            self::loadFile($absolutePath);
            return true;
        } catch (\Throwable $e) {
            SchemaErrors::add($absolutePath, $e);
            if (function_exists('error_log')) {
                error_log("CtrlField: schema file {$absolutePath} was skipped: {$e->getMessage()}");
            }
            return false;
        }
    }

    /**
     * Component-based auto-discovery: scans subdirectories for a fields.php file
     * and loads each one. Mirrors the CF3/Flynt pattern where every component
     * defines its own fields co-located with its template.
     *
     * Directory structure expected:
     *   components/
     *     ModuleHero/
     *       fields.php   ← loaded automatically
     *       ModuleHero.blade.php
     *     ModuleCards/
     *       fields.php
     *
     * @param string $componentsDir Absolute path to the components directory.
     * @param string $fieldsFile    Name of the field registration file (default: fields.php).
     */
    public static function loadComponentsDirectory(
        string $componentsDir,
        string $fieldsFile = 'fields.php',
    ): void {
        if (! is_dir($componentsDir)) {
            return;
        }

        $subdirs = glob(rtrim($componentsDir, '/\\') . '/*', GLOB_ONLYDIR);

        if ($subdirs === false) {
            return;
        }

        sort($subdirs);

        foreach ($subdirs as $dir) {
            $file = rtrim($dir, '/\\') . '/' . $fieldsFile;
            if (file_exists($file)) {
                self::loadFileSafely($file);
            }
        }
    }
}
