<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Commands;

use CtrlField\Builder\OptionsPage;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Registry\PendingCloneRegistry;
use CtrlField\Storage\Drivers\WpOptionsDriver;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\OptionsAdapter;
use CtrlField\Storage\PostMetaAdapter;

/**
 * WP-CLI command: wp ctrlfield validate
 *
 * Excluded from PHPStan — references WP_CLI, WP_Query, and WP drivers.
 *
 * Reports posts and options pages whose stored schema_version is behind
 * SchemaVersion::CURRENT without performing any migration.
 *
 * Usage:
 *   wp ctrlfield validate
 *   wp ctrlfield validate --post-type=portfolio
 */
class ValidateCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $postType = $assocArgs['post-type'] ?? 'any';

        $issues  = 0;
        $issues += $this->validatePendingClones();
        $issues += $this->validateSchemaFiles();
        $issues += $this->validatePostMeta($postType);
        $issues += $this->validateOptionsPages();

        if ($issues === 0) {
            \WP_CLI::success('All stored data matches the current schema version. Nothing to migrate.');
        } else {
            \WP_CLI::warning(
                sprintf(
                    '%d mismatch(es) found. Run "wp ctrlfield migrate" to upgrade.',
                    $issues
                )
            );
        }
    }

    private function validatePendingClones(): int
    {
        if (! PendingCloneRegistry::hasPending()) {
            return 0;
        }

        $issues = 0;

        foreach (PendingCloneRegistry::getPending() as $sourceKey => $groups) {
            foreach ($groups as $group) {
                \WP_CLI::log(sprintf(
                    '✗ Unresolved clone: group "%s" references library group "%s" which is not registered.',
                    $group->getKey(),
                    $sourceKey,
                ));
                $issues++;
            }
        }

        return $issues;
    }

    private function validateSchemaFiles(): int
    {
        if (! defined('CTRLFIELD_SCHEMA_PATH') || ! is_string(\CTRLFIELD_SCHEMA_PATH)) {
            return 0;
        }

        $dir = \CTRLFIELD_SCHEMA_PATH;

        if (! is_dir($dir)) {
            \WP_CLI::warning("CTRLFIELD_SCHEMA_PATH '{$dir}' does not exist.");
            return 1;
        }

        $files = glob(rtrim($dir, '/\\') . '/*.php') ?: [];
        sort($files);

        $issues = 0;

        foreach ($files as $file) {
            $output = [];
            $code   = 0;
            exec(sprintf('php -l %s 2>&1', escapeshellarg($file)), $output, $code);

            $relative = basename($file);

            if ($code === 0) {
                \WP_CLI::log("✓ schema/{$relative} — valid PHP");
            } else {
                $error = implode(' ', $output);
                \WP_CLI::log("✗ schema/{$relative} — {$error}");
                $issues++;
            }
        }

        return $issues;
    }

    private function validatePostMeta(string $postType): int
    {
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
        $issues  = 0;

        foreach ($postIds as $postId) {
            $raw = $adapter->loadRaw($postId);

            if ($raw === null) {
                continue;
            }

            if ($raw['schema_version'] < SchemaVersion::CURRENT) {
                \WP_CLI::log(sprintf(
                    'Post %d (%s): stored v%d, current v%d — migration needed.',
                    $postId,
                    get_post_type($postId) ?: 'unknown',
                    $raw['schema_version'],
                    SchemaVersion::CURRENT,
                ));
                $issues++;
            }
        }

        return $issues;
    }

    private function validateOptionsPages(): int
    {
        $driver  = new WpOptionsDriver();
        $issues  = 0;

        foreach (OptionsPage::all() as $page) {
            $raw = $driver->get(OptionsAdapter::KEY_PREFIX . $page->getKey(), null);

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                \WP_CLI::warning(sprintf('Options page "%s": stored data is malformed JSON.', $page->getKey()));
                $issues++;
                continue;
            }

            if (! is_array($decoded)) {
                continue;
            }

            $storedVersion = isset($decoded['schema_version']) && is_int($decoded['schema_version'])
                ? $decoded['schema_version']
                : 0;

            if ($storedVersion < SchemaVersion::CURRENT) {
                \WP_CLI::log(sprintf(
                    'Options page "%s": stored v%d, current v%d — migration needed.',
                    $page->getKey(),
                    $storedVersion,
                    SchemaVersion::CURRENT,
                ));
                $issues++;
            }
        }

        return $issues;
    }
}
