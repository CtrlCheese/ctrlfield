<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Builder\FieldGroup;
use CtrlField\Core\Migration\MigrationEngine;
use CtrlField\Core\Migration\MigrationRecord;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

/**
 * What the Schema Inspector reads from the database — coverage, payloads
 * under an older schema version, values left for fields that no longer
 * exist — and the two fixes it offers (migrate, clean up), each with a
 * dry run. Excluded from PHPStan — uses $wpdb.
 */
final class InspectorData
{
    /** Posts looked at per post type when searching for left-over values. */
    private const SCAN_LIMIT = 2000;

    /** @return array{0: int, 1: int} posts with CtrlField data, published posts of the type */
    public function coverage(string $postType): array
    {
        global $wpdb;
        if (! isset($wpdb)) {
            return [0, 0];
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $postType
        ));
        $with = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'",
            PostMetaAdapter::META_KEY,
            $postType
        ));

        return [$with, $total];
    }

    /** Posts (any type) that have CtrlField data. */
    public function postsWithData(): int
    {
        global $wpdb;
        if (! isset($wpdb)) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT m.post_id) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = %s AND p.post_type <> 'revision'",
            PostMetaAdapter::META_KEY
        ));
    }

    /**
     * Payloads stored under an older schema version (read through the
     * migrations, written back by migrate()).
     *
     * @return list<int> post ids
     */
    public function stalePosts(): array
    {
        $ids = [];
        foreach ($this->payloads(null) as $postId => $raw) {
            if ($raw['schema_version'] < SchemaVersion::CURRENT) {
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * Values stored for keys no field group of that post type defines any
     * more (a key renamed or a field removed): post type => key => posts.
     *
     * @param  array<string, FieldGroup> $groups
     * @return array<string, array<string, int>>
     */
    public function orphans(array $groups): array
    {
        $defined = self::definedKeys($groups);
        $out     = [];
        foreach (array_keys($defined['types']) as $type) {
            $known = array_flip([...$defined['types'][$type], ...$defined['everywhere']]);
            foreach ($this->payloads($type) as $raw) {
                foreach (array_keys($raw['fields']) as $key) {
                    $key = (string) $key;
                    if (! isset($known[$key]) && ! str_starts_with($key, '_')) {
                        $out[$type][$key] = ($out[$type][$key] ?? 0) + 1;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * How many posts of a type have a value in each top-level field.
     *
     * @return array{total: int, keys: array<string, int>} total = posts with CtrlField data looked at
     */
    public function usage(string $postType): array
    {
        $total = 0;
        $keys  = [];
        foreach ($this->payloads($postType) as $raw) {
            $total++;
            foreach ($raw['fields'] as $key => $value) {
                if (self::filled($value)) {
                    $keys[(string) $key] = ($keys[(string) $key] ?? 0) + 1;
                }
            }
        }

        return ['total' => $total, 'keys' => $keys];
    }

    /**
     * Posts whose flexible content uses each layout (component) key, at any depth.
     *
     * @return array<string, list<int>> layout key => post ids
     */
    public function layoutUsage(): array
    {
        $out = [];
        foreach ($this->payloads(null) as $postId => $raw) {
            foreach (array_keys(self::layouts($raw['fields'])) as $layout) {
                $out[$layout][] = $postId;
            }
        }

        return $out;
    }

    /** Whether a stored value counts as filled in: not null, "", false, or made only of empty values. */
    public static function filled(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($key !== '_layout' && self::filled($item)) {
                    return true;
                }
            }
            return false;
        }

        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * The "_layout" keys found anywhere in a payload.
     *
     * @param  array<mixed>        $value
     * @return array<string, true>
     */
    public static function layouts(array $value): array
    {
        $found = [];
        if (isset($value['_layout']) && is_string($value['_layout'])) {
            $found[$value['_layout']] = true;
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                $found += self::layouts($item);
            }
        }

        return $found;
    }

    /**
     * Writes payloads stored under an older version at the current one,
     * through the registered migrations.
     *
     * @return array{done: int, failed: int}
     */
    public function migrate(bool $dryRun): array
    {
        $adapter = new PostMetaAdapter(new WpPostMetaDriver(), SchemaVersion::CURRENT);
        $records = [];
        foreach ($this->payloads(null) as $postId => $raw) {
            if ($raw['schema_version'] < SchemaVersion::CURRENT) {
                $records[] = new MigrationRecord(postId: $postId, storedVersion: $raw['schema_version'], data: $raw['fields']);
            }
        }
        $done = $failed = 0;
        foreach ((new MigrationEngine(MigrationEngine::registered()))->run($records, $adapter, $dryRun) as $result) {
            $result->success ? $done++ : $failed++;
        }

        return ['done' => $done, 'failed' => $failed];
    }

    /**
     * Removes the given left-over keys from the posts of a type.
     *
     * @param  list<string> $keys
     * @return int posts changed (or that would be)
     */
    public function cleanOrphans(string $postType, array $keys, bool $dryRun): int
    {
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $changed = 0;
        foreach ($this->payloads($postType) as $postId => $raw) {
            $fields = $raw['fields'];
            foreach ($keys as $key) {
                unset($fields[$key]);
            }
            if (count($fields) === count($raw['fields'])) {
                continue;
            }
            $changed++;
            if (! $dryRun) {
                $adapter->save($postId, $fields, $raw['schema_version']);
            }
        }

        return $changed;
    }

    /**
     * Keys defined per post type, and keys of groups on posts that have no
     * post type rule (templates, formats…), which count everywhere.
     *
     * @param  array<string, FieldGroup> $groups
     * @return array{types: array<string, list<string>>, everywhere: list<string>}
     */
    public static function definedKeys(array $groups): array
    {
        $types      = [];
        $everywhere = [];
        foreach ($groups as $group) {
            $keys = array_map(static fn ($f): string => $f->getKey(), array_values($group->getFields()));
            $list = SchemaAnalyzer::postTypes($group);
            if ($list === [] && SchemaAnalyzer::place($group) === 'post') {
                $everywhere = [...$everywhere, ...$keys];
            }
            foreach ($list as $type) {
                $types[$type] = [...($types[$type] ?? []), ...$keys];
            }
        }

        return ['types' => $types, 'everywhere' => $everywhere];
    }

    /**
     * Stored payloads (raw: fields + schema_version), of one post type or all
     * (revisions keep their own copy and are left out).
     *
     * @return \Generator<int, array{schema_version: int, fields: array<string, mixed>}>
     */
    private function payloads(?string $postType): \Generator
    {
        global $wpdb;
        if (! isset($wpdb)) {
            return;
        }

        $rows = $postType === null
            ? $wpdb->get_results($wpdb->prepare(
                "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
                 WHERE m.meta_key = %s AND p.post_type <> 'revision' LIMIT %d",
                PostMetaAdapter::META_KEY,
                self::SCAN_LIMIT * 5
            ))
            : $wpdb->get_results($wpdb->prepare(
                "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
                 WHERE m.meta_key = %s AND p.post_type = %s LIMIT %d",
                PostMetaAdapter::META_KEY,
                $postType,
                self::SCAN_LIMIT
            ));

        foreach ((array) $rows as $row) {
            $decoded = json_decode((string) $row->meta_value, true);
            if (! is_array($decoded)) {
                continue;
            }
            yield (int) $row->post_id => [
                'schema_version' => is_int($decoded['schema_version'] ?? null) ? $decoded['schema_version'] : 0,
                'fields'         => is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [],
            ];
        }
    }
}
