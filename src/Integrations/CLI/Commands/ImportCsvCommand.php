<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Commands;

use FieldForge\Data\CsvImporter;

/**
 * WP-CLI command: wp fieldforge import-csv
 *
 * Imports post field data from a CSV file.
 *
 * Usage:
 *   wp fieldforge import-csv portfolio.csv --post-type=portfolio
 *   wp fieldforge import-csv portfolio.csv --post-type=portfolio --update-existing
 *   wp fieldforge import-csv portfolio.csv --post-type=portfolio --create-missing
 *   wp fieldforge import-csv portfolio.csv --post-type=portfolio --dry-run
 *
 * Excluded from PHPStan — references WP_CLI not available outside CLI runtime.
 */
class ImportCsvCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $filePath = $args[0] ?? '';
        if ($filePath === '') {
            \WP_CLI::error('Usage: wp fieldforge import-csv <file> --post-type=<post_type>');
            return;
        }

        if (! file_exists($filePath)) {
            \WP_CLI::error(sprintf('File not found: %s', $filePath));
            return;
        }

        $postType = $assocArgs['post-type'] ?? '';
        if ($postType === '') {
            \WP_CLI::error('--post-type is required.');
            return;
        }

        $dryRun         = isset($assocArgs['dry-run']);
        $updateExisting = isset($assocArgs['update-existing']);
        $createMissing  = isset($assocArgs['create-missing']);

        if ($dryRun) {
            \WP_CLI::log('[dry-run] No data will be written.');
        }

        $importer = new CsvImporter();
        $stats    = $importer->import(
            $filePath,
            $postType,
            $dryRun,
            $updateExisting,
            $createMissing,
        );

        foreach ($stats['errors'] as $error) {
            \WP_CLI::warning($error);
        }

        \WP_CLI::success(sprintf(
            'Summary: %d updated, %d created, %d skipped, %d error(s).',
            $stats['updated'],
            $stats['created'],
            $stats['skipped'],
            count($stats['errors']),
        ));
    }
}
