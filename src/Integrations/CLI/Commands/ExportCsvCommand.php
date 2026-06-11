<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Commands;

use FieldForge\Data\CsvExporter;

/**
 * WP-CLI command: wp fieldforge export-csv
 *
 * Exports post field data as CSV rows for a given post type.
 *
 * Usage:
 *   wp fieldforge export-csv --post-type=portfolio
 *   wp fieldforge export-csv --post-type=portfolio --fields=client_name,status
 *   wp fieldforge export-csv --post-type=portfolio --status=publish > output.csv
 *
 * Excluded from PHPStan — references WP_CLI not available outside CLI runtime.
 */
class ExportCsvCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $postType = $assocArgs['post-type'] ?? '';
        if ($postType === '') {
            \WP_CLI::error('--post-type is required. Usage: wp fieldforge export-csv --post-type=<post_type>');
            return;
        }

        $fieldKeys  = isset($assocArgs['fields']) && $assocArgs['fields'] !== ''
            ? array_map('trim', explode(',', $assocArgs['fields']))
            : [];

        $postStatus = $assocArgs['status'] ?? 'any';

        $exporter = new CsvExporter();
        $count    = 0;

        foreach ($exporter->export($postType, $fieldKeys, $postStatus) as $row) {
            echo $row;
            $count++;
        }

        // $count includes the header row — subtract 1 for the data row count
        \WP_CLI::log(sprintf('Exported %d post(s) for post type "%s".', max(0, $count - 1), $postType));
    }
}
