<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Storage;

use CtrlField\Storage\RelationshipAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the pure-logic computeDiff() method (AC 10).
 * DB operations (save/load) require integration tests with a real wpdb.
 */
class RelationshipAdapterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // computeDiff (AC 10)
    // -------------------------------------------------------------------------

    public function testInsertOnly(): void
    {
        $existing = [];
        $newIds   = [5, 10, 15];

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertSame([], $diff['delete']);
        self::assertSame([0 => 5, 1 => 10, 2 => 15], $diff['insert']);
        self::assertSame([], $diff['update']);
    }

    public function testDeleteOnly(): void
    {
        $existing = [5, 10, 15];
        $newIds   = [];

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertEqualsCanonicalizing([5, 10, 15], $diff['delete']);
        self::assertSame([], $diff['insert']);
        self::assertSame([], $diff['update']);
    }

    public function testMixedInsertDeleteUpdate(): void
    {
        $existing = [5, 10, 15];
        $newIds   = [10, 20, 15]; // delete 5, insert 20, update 10 and 15

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertSame([5], $diff['delete']);
        self::assertSame([1 => 20], $diff['insert']); // sort_order 1
        self::assertSame([0 => 10, 2 => 15], $diff['update']);
    }

    public function testNoDiffWhenIdentical(): void
    {
        $existing = [1, 2, 3];
        $newIds   = [1, 2, 3];

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertSame([], $diff['delete']);
        self::assertSame([], $diff['insert']);
        self::assertSame([0 => 1, 1 => 2, 2 => 3], $diff['update']);
    }

    public function testReorderOnlyProducesOnlyUpdates(): void
    {
        $existing = [1, 2, 3];
        $newIds   = [3, 1, 2];

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertSame([], $diff['delete']);
        self::assertSame([], $diff['insert']);
        self::assertSame([0 => 3, 1 => 1, 2 => 2], $diff['update']);
    }

    public function testFullReplacement(): void
    {
        $existing = [1, 2];
        $newIds   = [3, 4];

        $diff = RelationshipAdapter::computeDiff($existing, $newIds);

        self::assertEqualsCanonicalizing([1, 2], $diff['delete']);
        self::assertSame([0 => 3, 1 => 4], $diff['insert']);
        self::assertSame([], $diff['update']);
    }

    // -------------------------------------------------------------------------
    // createTableSql — the pivot table must exist before save/load
    // -------------------------------------------------------------------------

    public function testCreateTableSqlHasColumnsAndKeysUsedBySaveAndLoad(): void
    {
        $sql = RelationshipAdapter::createTableSql('wp_ctrlf_rel_related', 'DEFAULT CHARSET=utf8mb4');

        self::assertStringStartsWith('CREATE TABLE wp_ctrlf_rel_related (', $sql);
        foreach (['source_id', 'target_id', 'field_key', 'sort_order'] as $column) {
            self::assertStringContainsString("  {$column} ", $sql);
        }
        // dbDelta needs two spaces after PRIMARY KEY.
        self::assertStringContainsString('PRIMARY KEY  (source_id,field_key,target_id)', $sql);
        self::assertStringContainsString('KEY target_lookup (target_id,field_key)', $sql);
        self::assertStringEndsWith(') DEFAULT CHARSET=utf8mb4;', $sql);
    }
}
