<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

use CtrlField\Schema\JsonGroup;

/**
 * Field groups created in CtrlField → Field Groups.
 *
 * Each group is a JSON file in <theme>/ctrlfield-json/<key>.json, so it can be
 * committed and deployed with the theme like ACF's Local JSON. When that
 * folder cannot be written (read-only hosting, DISALLOW_FILE_MODS) groups are
 * kept in the ctrlfield_ui_field_groups option instead. A file wins over an
 * option entry with the same key.
 *
 * The folder is changed with the CTRLFIELD_JSON_PATH constant or the
 * ctrlfield/json_path filter.
 */
final class FieldGroupRepository
{
    public const OPTION_KEY = 'ctrlfield_ui_field_groups';

    public const SOURCE_FILE   = 'file';
    public const SOURCE_OPTION = 'option';

    public function __construct(private readonly ?string $path = null) {}

    public function directory(): string
    {
        if ($this->path !== null) {
            return rtrim($this->path, '/\\');
        }

        $path = defined('CTRLFIELD_JSON_PATH')
            ? (string) CTRLFIELD_JSON_PATH
            : get_stylesheet_directory() . '/ctrlfield-json';

        return rtrim((string) apply_filters('ctrlfield/json_path', $path), '/\\');
    }

    /** Can groups be saved as files? */
    public function canWriteFiles(): bool
    {
        // Honours DISALLOW_FILE_MODS (read-only deployments).
        if (function_exists('wp_is_file_mod_allowed') && ! wp_is_file_mod_allowed('ctrlfield_json')) {
            return false;
        }

        $dir = $this->directory();

        return is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
    }

    /**
     * Every stored group, normalized, keyed by group key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $groups = [];

        foreach ($this->optionGroups() as $key => $raw) {
            $groups[$key] = $this->clean($raw, self::SOURCE_OPTION);
        }

        foreach ($this->fileGroups() as $key => $raw) {
            $groups[$key] = $this->clean($raw, self::SOURCE_FILE);
        }

        ksort($groups);

        return $groups;
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Store a normalized group. Returns where it went.
     *
     * @param array<string, mixed> $group
     */
    public function save(array $group, ?string $previousKey = null): string
    {
        $key = (string) $group['key'];
        unset($group['_source'], $group['_errors']);
        $group['modified'] = time();

        if ($previousKey !== null && $previousKey !== $key) {
            $this->delete($previousKey);
        }

        if ($this->canWriteFiles() && $this->writeFile($key, $group)) {
            // Moved to a file: drop the old database copy.
            $this->deleteOption($key);
            return self::SOURCE_FILE;
        }

        $rows       = $this->optionGroups();
        $rows[$key] = $group;
        update_option(self::OPTION_KEY, $rows, true);

        return self::SOURCE_OPTION;
    }

    public function delete(string $key): void
    {
        if (! $this->isValidKey($key)) {
            return;
        }

        $file = $this->directory() . '/' . $key . '.json';
        if (is_file($file)) {
            wp_delete_file($file);
        }

        $this->deleteOption($key);
    }

    // -------------------------------------------------------------------------

    /**
     * Normalize stored data: files can be edited by hand or come from another
     * site, so they get the same checks as the admin form.
     *
     * @param  array<mixed> $raw
     * @return array<string, mixed>
     */
    private function clean(array $raw, string $source): array
    {
        [$group, $errors] = JsonGroup::normalize($raw);
        $group['_source'] = $source;
        $group['_errors'] = $errors;

        return $group;
    }

    /** @return array<string, array<mixed>> */
    private function fileGroups(): array
    {
        $dir   = $this->directory();
        $files = is_dir($dir) ? glob($dir . '/*.json') : false;
        $out   = [];

        foreach ($files === false ? [] : $files as $file) {
            $key = basename($file, '.json');
            if (! $this->isValidKey($key)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                $data = [];
            }
            // The file name is the key: a copied file cannot claim another group.
            $data['key'] = $key;
            $out[$key]   = $data;
        }

        return $out;
    }

    /** @return array<string, array<mixed>> */
    private function optionGroups(): array
    {
        $rows = get_option(self::OPTION_KEY, []);
        $out  = [];

        foreach (is_array($rows) ? $rows : [] as $key => $row) {
            if (is_string($key) && is_array($row) && $this->isValidKey($key)) {
                $row['key'] = $key;
                $out[$key]  = $row;
            }
        }

        return $out;
    }

    private function deleteOption(string $key): void
    {
        $rows = $this->optionGroups();
        if (isset($rows[$key])) {
            unset($rows[$key]);
            update_option(self::OPTION_KEY, $rows, true);
        }
    }

    /** @param array<string, mixed> $group */
    private function writeFile(string $key, array $group): bool
    {
        $dir = $this->directory();
        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return false;
        }

        $json = wp_json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) && file_put_contents($dir . '/' . $key . '.json', $json . "\n", LOCK_EX) !== false;
    }

    private function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key);
    }
}
