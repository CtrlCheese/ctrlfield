<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

/**
 * Writes a field group as a PHP file into the schema folder — the first of
 * CTRLFIELD_SCHEMA_PATH / the 'ctrlfield/schema_paths' filter, the folder
 * loaded on init:5, where code takes precedence over JSON groups.
 *
 * The file is PHP the site will run, so:
 * - only with file changes allowed (DISALLOW_FILE_MODS) and file editing
 *   allowed (DISALLOW_FILE_EDIT, the switch that keeps admins from running
 *   their own PHP);
 * - on multisite, only for super admins: the schema folder is shared by
 *   every site of the network;
 * - only "<key>.php" inside that folder, parsed before it is written;
 * - an existing file is replaced only when asked to, and only when this
 *   writer made it (MARKER), never a file a developer wrote.
 */
final class PhpFileWriter
{
    public const ERR_DISABLED = 'disabled';
    public const ERR_NO_FOLDER = 'no_folder';
    public const ERR_NOT_WRITABLE = 'not_writable';
    public const ERR_EXISTS = 'exists';
    public const ERR_INVALID = 'invalid';
    public const ERR_FAILED = 'failed';
    public const ERR_NOT_ALLOWED = 'not_allowed';
    public const ERR_NOT_OURS = 'not_ours';

    /** Second line of every file this writer makes. */
    public const MARKER = '// Saved by CtrlField → Field Groups. Saving the group again replaces this file.';

    public function __construct(private readonly ?string $directory = null) {}

    /** The schema folder, without trailing slash, or null when none is set up. */
    public function directory(): ?string
    {
        if ($this->directory !== null) {
            return rtrim($this->directory, '/\\');
        }

        $paths = defined('CTRLFIELD_SCHEMA_PATH') ? [(string) CTRLFIELD_SCHEMA_PATH] : [];
        if (function_exists('apply_filters')) {
            $paths = (array) apply_filters('ctrlfield/schema_paths', $paths);
        }
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                return rtrim($path, '/\\');
            }
        }

        return null;
    }

    /** Why files cannot be written (an ERR_* code), or null when they can. */
    public function problem(): ?string
    {
        if (function_exists('wp_is_file_mod_allowed') && ! wp_is_file_mod_allowed('ctrlfield_php')) {
            return self::ERR_DISABLED;
        }
        if ((defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT)
            || (function_exists('is_multisite') && is_multisite() && ! is_super_admin())) {
            return self::ERR_NOT_ALLOWED;
        }
        $dir = $this->directory();
        if ($dir === null) {
            return self::ERR_NO_FOLDER;
        }

        return (is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir))) ? null : self::ERR_NOT_WRITABLE;
    }

    public function path(string $key): ?string
    {
        $dir = $this->directory();

        return $dir !== null && self::validKey($key) ? $dir . '/' . $key . '.php' : null;
    }

    public function exists(string $key): bool
    {
        $path = $this->path($key);

        return $path !== null && file_exists($path);
    }

    /**
     * @return string the written file's path
     * @throws \RuntimeException with an ERR_* code as message
     */
    public function write(string $key, string $code, bool $overwrite = false): string
    {
        $problem = $this->problem();
        if ($problem !== null) {
            throw new \RuntimeException($problem);
        }
        $path = $this->path($key);
        if ($path === null || ! self::parses($code)) {
            throw new \RuntimeException(self::ERR_INVALID);
        }
        if (file_exists($path) && ! $overwrite) {
            throw new \RuntimeException(self::ERR_EXISTS);
        }
        if (file_exists($path) && ! self::isOurs($path)) {
            throw new \RuntimeException(self::ERR_NOT_OURS);
        }
        $code = (string) preg_replace('/^<\?php[ \t]*\R/', "<?php\n" . self::MARKER . "\n", $code, 1);

        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            throw new \RuntimeException(self::ERR_FAILED);
        }
        // Written next to the target and renamed: a half-written file is never loaded.
        $tmp = $dir . '/.' . $key . '.php.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $code, LOCK_EX) === false || ! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException(self::ERR_FAILED);
        }

        return $path;
    }

    /** Was the file made by this writer? */
    public static function isOurs(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 512);

        return is_string($head) && str_contains($head, self::MARKER);
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9_-]{1,100}$/', $key);
    }

    /** Whether the code is valid PHP (parsed, never run). */
    public static function parses(string $code): bool
    {
        try {
            return token_get_all($code, TOKEN_PARSE) !== [];
        } catch (\ParseError) {
            return false;
        }
    }
}
