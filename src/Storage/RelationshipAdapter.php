<?php

declare(strict_types=1);

namespace CtrlField\Storage;

final class RelationshipAdapter
{
    /** Option listing pivot tables already created, so dbDelta runs once per table. */
    public const TABLES_OPTION = '_ctrlfield_rel_tables';

    /** @var array<string, true> per-request cache of verified tables */
    private static array $ensured = [];

    /**
     * CREATE TABLE statement for a relationship pivot table.
     * Public for unit testing without running dbDelta.
     */
    public static function createTableSql(string $tableName, string $charsetCollate = ''): string
    {
        return "CREATE TABLE {$tableName} (
  source_id bigint(20) unsigned NOT NULL,
  target_id bigint(20) unsigned NOT NULL,
  field_key varchar(191) NOT NULL,
  sort_order int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (source_id,field_key,target_id),
  KEY target_lookup (target_id,field_key)
) {$charsetCollate};";
    }

    /**
     * Create the pivot table on first use. Nothing else creates it: without this
     * every relationship value was silently dropped.
     */
    public static function ensureTable(string $tableName): void
    {
        if (isset(self::$ensured[$tableName])) {
            return;
        }

        $created = get_option(self::TABLES_OPTION, []);
        $created = is_array($created) ? $created : [];

        if (! in_array($tableName, $created, true)) {
            global $wpdb;

            if (! function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            dbDelta(self::createTableSql($tableName, $wpdb->get_charset_collate()));

            $created[] = $tableName;
            update_option(self::TABLES_OPTION, array_values(array_unique($created)), false);
        }

        self::$ensured[$tableName] = true;
    }

    /**
     * Remove every pivot row that points to or from a deleted post, in all
     * relationship tables created so far (they were left behind as orphans).
     */
    public static function deleteForPost(int $postId): void
    {
        global $wpdb;

        $tables = get_option(self::TABLES_OPTION, []);

        foreach (is_array($tables) ? $tables : [] as $table) {
            if (! is_string($table) || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "DELETE FROM `{$table}` WHERE source_id = %d OR target_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $postId,
                $postId,
            ));
        }
    }

    /**
     * Save a relationship (forward direction).
     * Diffs against existing rows: inserts new, deletes removed, updates sort_order.
     *
     * @param array<int, int> $targetIds ordered list of target post IDs
     */
    public static function save(int $sourceId, string $fieldKey, array $targetIds, string $tableName): void
    {
        global $wpdb;

        self::ensureTable($tableName);

        $existing = self::load($sourceId, $fieldKey, $tableName);
        $diff     = self::computeDiff($existing, $targetIds);

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($diff['delete'] as $targetId) {
                $wpdb->delete($tableName, [
                    'source_id' => $sourceId,
                    'target_id' => $targetId,
                    'field_key' => $fieldKey,
                ]);
            }

            foreach ($diff['insert'] as $sortOrder => $targetId) {
                $wpdb->insert($tableName, [
                    'source_id'  => $sourceId,
                    'target_id'  => $targetId,
                    'sort_order' => $sortOrder,
                    'field_key'  => $fieldKey,
                ]);
            }

            foreach ($diff['update'] as $sortOrder => $targetId) {
                $wpdb->update(
                    $tableName,
                    ['sort_order' => $sortOrder],
                    ['source_id' => $sourceId, 'target_id' => $targetId, 'field_key' => $fieldKey],
                );
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Load ordered target IDs for a source post.
     *
     * @return array<int, int>
     */
    public static function load(int $sourceId, string $fieldKey, string $tableName): array
    {
        global $wpdb;

        self::ensureTable($tableName);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT target_id FROM `{$tableName}` WHERE source_id = %d AND field_key = %s ORDER BY sort_order ASC",
                $sourceId,
                $fieldKey,
            ),
            ARRAY_A,
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $r) => (int) $r['target_id'], $rows);
    }

    /**
     * Reverse lookup: which source posts point to a given target?
     *
     * @return array<int, int>
     */
    public static function loadReverse(int $targetId, string $fieldKey, string $tableName): array
    {
        global $wpdb;

        self::ensureTable($tableName);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_id FROM `{$tableName}` WHERE target_id = %d AND field_key = %s ORDER BY sort_order ASC",
                $targetId,
                $fieldKey,
            ),
            ARRAY_A,
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $r) => (int) $r['source_id'], $rows);
    }

    /**
     * Compute insert/delete/update diff between existing and desired target ID lists.
     *
     * @param  array<int, int> $existing  current ordered target IDs from DB
     * @param  array<int, int> $newIds    desired ordered target IDs
     * @return array{insert: array<int, int>, delete: array<int, int>, update: array<int, int>}
     *         Keys of insert/update arrays are the desired sort_order (0-indexed).
     */
    public static function computeDiff(array $existing, array $newIds): array
    {
        $existingSet = array_flip($existing);
        $newSet      = array_flip($newIds);

        $toDelete = [];
        $toInsert = [];
        $toUpdate = [];

        foreach ($existing as $id) {
            if (! isset($newSet[$id])) {
                $toDelete[] = $id;
            }
        }

        foreach ($newIds as $sortOrder => $id) {
            if (! isset($existingSet[$id])) {
                $toInsert[$sortOrder] = $id;
            } else {
                $toUpdate[$sortOrder] = $id;
            }
        }

        return [
            'insert' => $toInsert,
            'delete' => $toDelete,
            'update' => $toUpdate,
        ];
    }
}
