<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Commands;

use FieldForge\Builder\CPT;
use FieldForge\Builder\Taxonomy;
use FieldForge\Registry\FieldRegistry;

/**
 * WP-CLI command: wp fieldforge import
 *
 * Excluded from PHPStan — references WP_CLI not available outside CLI runtime.
 *
 * Reads a JSON file produced by `wp fieldforge export` and compares it
 * against the current PHP schema, reporting additions and removals.
 * No writes are performed — this is a validation/diff command.
 *
 * Usage:
 *   wp fieldforge import --file=<path>
 */
class ImportCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $assocArgs['file'] ?? null;

        if ($file === null) {
            \WP_CLI::error('--file is required. Usage: wp fieldforge import --file=<path>');
            return;
        }

        if (! file_exists($file)) {
            \WP_CLI::error(sprintf('File not found: %s', $file));
            return;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            \WP_CLI::error(sprintf('Cannot read file: %s', $file));
            return;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            \WP_CLI::error(sprintf('Invalid JSON: %s', $e->getMessage()));
            return;
        }

        if (! is_array($data)) {
            \WP_CLI::error('Invalid export format: root element must be a JSON object.');
            return;
        }

        $issues = 0;
        $issues += $this->compareCpts($data['cpts'] ?? []);
        $issues += $this->compareTaxonomies($data['taxonomies'] ?? []);
        $issues += $this->compareFieldGroups($data['field_groups'] ?? []);

        if ($issues === 0) {
            \WP_CLI::success('Import file matches current PHP schema. No differences found.');
        } else {
            \WP_CLI::warning(sprintf('%d difference(s) found between import file and current schema.', $issues));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $imported
     */
    private function compareCpts(array $imported): int
    {
        $current     = CPT::all();
        $importedMap = [];

        foreach ($imported as $cpt) {
            $key              = is_string($cpt['post_type'] ?? null) ? $cpt['post_type'] : '';
            $importedMap[$key] = $cpt;
        }

        $issues = 0;

        foreach ($importedMap as $key => $_) {
            if (! isset($current[$key])) {
                \WP_CLI::warning(sprintf('CPT "%s": present in import file but not in current PHP schema.', $key));
                $issues++;
            }
        }

        foreach (array_keys($current) as $key) {
            if (! isset($importedMap[$key])) {
                \WP_CLI::log(sprintf('CPT "%s": registered in PHP but absent from import file.', $key));
                $issues++;
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $imported
     */
    private function compareTaxonomies(array $imported): int
    {
        $current     = Taxonomy::all();
        $importedMap = [];

        foreach ($imported as $tax) {
            $key              = is_string($tax['taxonomy'] ?? null) ? $tax['taxonomy'] : '';
            $importedMap[$key] = $tax;
        }

        $issues = 0;

        foreach ($importedMap as $key => $_) {
            if (! isset($current[$key])) {
                \WP_CLI::warning(sprintf('Taxonomy "%s": present in import file but not in current PHP schema.', $key));
                $issues++;
            }
        }

        foreach (array_keys($current) as $key) {
            if (! isset($importedMap[$key])) {
                \WP_CLI::log(sprintf('Taxonomy "%s": registered in PHP but absent from import file.', $key));
                $issues++;
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $imported
     */
    private function compareFieldGroups(array $imported): int
    {
        $current     = FieldRegistry::all();
        $importedMap = [];

        foreach ($imported as $group) {
            $key              = is_string($group['key'] ?? null) ? $group['key'] : '';
            $importedMap[$key] = $group;
        }

        $issues = 0;

        foreach ($importedMap as $key => $_) {
            if (! isset($current[$key])) {
                \WP_CLI::warning(sprintf('Field group "%s": present in import file but not in current PHP schema.', $key));
                $issues++;
            }
        }

        foreach (array_keys($current) as $key) {
            if (! isset($importedMap[$key])) {
                \WP_CLI::log(sprintf('Field group "%s": registered in PHP but absent from import file.', $key));
                $issues++;
            }
        }

        return $issues;
    }
}
