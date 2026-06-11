<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Commands;

use FieldForge\Core\Migration\MigrationEngine;
use FieldForge\Core\Migration\MigrationRecord;
use FieldForge\Core\Migration\MigrationResult;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;

/**
 * WP-CLI command: wp fieldforge migrate
 *
 * Excluded from PHPStan — references WP_CLI, WP_Query, and WP drivers.
 *
 * Usage:
 *   wp fieldforge migrate
 *   wp fieldforge migrate --dry-run
 *   wp fieldforge migrate --post-type=portfolio
 */
class MigrateCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $dryRun   = isset($assocArgs['dry-run']);
        $postType = $assocArgs['post-type'] ?? 'any';

        $adapter = new PostMetaAdapter(new WpPostMetaDriver(), SchemaVersion::CURRENT);

        $query = new \WP_Query([
            'post_type'      => $postType,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_key'       => PostMetaAdapter::META_KEY,
            'fields'         => 'ids',
        ]);

        /** @var array<int, int> $postIds */
        $postIds = $query->posts;

        $records = [];

        foreach ($postIds as $postId) {
            $raw = $adapter->loadRaw($postId);

            if ($raw === null) {
                continue;
            }

            if ($raw['schema_version'] >= SchemaVersion::CURRENT) {
                continue;
            }

            $records[] = new MigrationRecord(
                postId:        $postId,
                storedVersion: $raw['schema_version'],
                data:          $raw['fields'],
            );
        }

        if (empty($records)) {
            \WP_CLI::success('All posts are already at the current schema version. No migrations needed.');
            return;
        }

        $label  = $dryRun ? '[DRY RUN] ' : '';
        $engine = new MigrationEngine(MigrationEngine::registered());

        $results = $engine->run(
            records:    $records,
            adapter:    $adapter,
            dryRun:     $dryRun,
            onProgress: static function (MigrationResult $result) use ($label): void {
                if ($result->success) {
                    \WP_CLI::log(sprintf(
                        '%sPost %d: v%d → v%d',
                        $label,
                        $result->postId,
                        $result->fromVersion,
                        $result->toVersion,
                    ));
                } else {
                    \WP_CLI::warning(sprintf(
                        'Post %d failed: %s',
                        $result->postId,
                        $result->error,
                    ));
                }
            },
        );

        $succeeded = count(array_filter($results, static fn(MigrationResult $r): bool => $r->success));
        $failed    = count($results) - $succeeded;

        if ($dryRun) {
            \WP_CLI::success(sprintf('%d post(s) would be migrated.', $succeeded));
        } else {
            \WP_CLI::success(sprintf('%d migrated, %d failed.', $succeeded, $failed));
        }
    }
}
