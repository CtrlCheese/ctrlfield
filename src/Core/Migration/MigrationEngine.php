<?php

declare(strict_types=1);

namespace CtrlField\Core\Migration;

use CtrlField\Storage\Contracts\StorageAdapterInterface;

final class MigrationEngine
{
    /** @var array<int, MigrationInterface> Keyed and sorted by version. */
    private static array $registry = [];

    /** @var list<MigrationInterface> Sorted ascending by version at construction. */
    private array $migrations;

    /**
     * @param list<MigrationInterface> $migrations
     */
    public function __construct(array $migrations)
    {
        usort(
            $migrations,
            static fn(MigrationInterface $a, MigrationInterface $b): int => $a->version() <=> $b->version()
        );
        $this->migrations = $migrations;
    }

    // -------------------------------------------------------------------------
    // Instance: run migrations against a set of records
    // -------------------------------------------------------------------------

    /**
     * @param  list<MigrationRecord>              $records
     * @param  callable(MigrationResult): void    $onProgress
     * @return list<MigrationResult>
     */
    public function run(
        array                   $records,
        StorageAdapterInterface $adapter,
        bool                    $dryRun     = false,
        ?callable               $onProgress = null,
    ): array {
        $results = [];

        foreach ($records as $record) {
            $result    = $this->migrateRecord($record, $adapter, $dryRun);
            $results[] = $result;

            if ($onProgress !== null) {
                ($onProgress)($result);
            }
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Static registry — service providers register migrations at boot
    // -------------------------------------------------------------------------

    public static function register(MigrationInterface $migration): void
    {
        self::$registry[$migration->version()] = $migration;
        ksort(self::$registry);
    }

    /**
     * Fields stored under an older schema version, brought up to $to in memory
     * by the registered migrations (pure functions) — nothing is written; `wp
     * ctrlfield migrate` stores the result. Null when a migration fails, so the
     * caller falls back to treating the payload as unreadable.
     *
     * Without this, a post or options page whose payload predates the current
     * version read as empty until someone ran the migration.
     *
     * @param  array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    public static function upgradeOnRead(array $fields, int $from, int $to): ?array
    {
        foreach (self::$registry as $version => $migration) {
            if ($version <= $from || $version > $to) {
                continue;
            }
            try {
                $fields = $migration->up($fields);
            } catch (\Throwable) {
                return null;
            }
        }

        return $fields;
    }

    /** @return list<MigrationInterface> */
    public static function registered(): array
    {
        return array_values(self::$registry);
    }

    public static function reset(): void
    {
        self::$registry = [];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function migrateRecord(
        MigrationRecord         $record,
        StorageAdapterInterface $adapter,
        bool                    $dryRun,
    ): MigrationResult {
        $applicable = array_values(array_filter(
            $this->migrations,
            static fn(MigrationInterface $m): bool => $m->version() > $record->storedVersion,
        ));

        if (empty($applicable)) {
            return new MigrationResult(
                postId:      $record->postId,
                success:     true,
                fromVersion: $record->storedVersion,
                toVersion:   $record->storedVersion,
                data:        $record->data,
            );
        }

        $currentData   = $record->data;
        $targetVersion = $record->storedVersion;

        foreach ($applicable as $migration) {
            try {
                $currentData   = $migration->up($currentData);
                $targetVersion = $migration->version();
            } catch (\Throwable $e) {
                // Rollback: ensure the stored data is the original, not a partial transform.
                if (! $dryRun) {
                    $adapter->save($record->postId, $record->data, $record->storedVersion);
                }

                return new MigrationResult(
                    postId:      $record->postId,
                    success:     false,
                    fromVersion: $record->storedVersion,
                    toVersion:   $targetVersion,
                    data:        $record->data,
                    error:       $e->getMessage(),
                );
            }
        }

        if (! $dryRun) {
            $adapter->save($record->postId, $currentData, $targetVersion);
        }

        return new MigrationResult(
            postId:      $record->postId,
            success:     true,
            fromVersion: $record->storedVersion,
            toVersion:   $targetVersion,
            data:        $currentData,
        );
    }
}
