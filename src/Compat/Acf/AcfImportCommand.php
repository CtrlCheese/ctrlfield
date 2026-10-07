<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Admin\FieldGroups\FieldGroupRepository;

/**
 * wp ctrlfield acf-import [--data] [--dry-run] [--group=<key>]
 *
 * Excluded from PHPStan — WP-CLI runtime class.
 */
final class AcfImportCommand
{
    /**
     * Import ACF field groups (and their values) into CtrlField.
     *
     * ## OPTIONS
     *
     * [--data]
     * : Also copy the values saved with ACF.
     *
     * [--dry-run]
     * : Show what would be imported without saving anything.
     *
     * [--group=<key>]
     * : Only this ACF group (group_…). Default: all.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function __invoke(array $args, array $assoc): void
    {
        $importer = new AcfImporter(new FieldGroupRepository());
        $sources  = $importer->sources();
        $keys     = isset($assoc['group']) ? [$assoc['group']] : array_keys($sources);

        if ($keys === [] || ! array_intersect($keys, array_keys($sources))) {
            \WP_CLI::error('No ACF field groups found.');
        }

        $dry    = isset($assoc['dry-run']);
        $report = $importer->import($keys, isset($assoc['data']), $dry);

        foreach ($report as $r) {
            \WP_CLI::log(sprintf('%s %s → %s (%d items with values)', $r['status'] === 'failed' ? '✗' : '✓', $r['title'], $r['key'], $r['objects']));
            foreach (array_merge($r['errors'], $r['warnings'], $r['skipped']) as $msg) {
                \WP_CLI::log('    • ' . $msg);
            }
        }

        \WP_CLI::success($dry ? 'Dry run: nothing was saved.' : sprintf('%d group(s) processed.', count($report)));
    }
}
