<?php

declare(strict_types=1);

namespace FieldForge\Data;

use FieldForge\Builder\FieldGroup;
use FieldForge\Registry\FieldRegistry;

/**
 * Exports post field data as CSV rows.
 *
 * Excluded from PHPStan — references WP_Query which is not available outside WP runtime.
 */
final class CsvExporter
{
    /**
     * Exports posts of a given type and their FieldForge field values.
     *
     * @param string[] $fieldKeys  Empty = all fields registered for this post type.
     * @return \Generator<string>  Yields the header row first, then one row per post.
     */
    public function export(
        string $postType,
        array  $fieldKeys = [],
        string $postStatus = 'any',
    ): \Generator {
        $groups    = $this->resolveGroups($postType);
        $allFields = $this->collectFields($groups, $fieldKeys);
        $headers   = array_merge(['post_id', 'post_title', 'post_status'], array_keys($allFields));

        yield $this->encodeRow($headers);

        $paged = 1;

        do {
            $query = new \WP_Query([
                'post_type'      => $postType,
                'post_status'    => $postStatus,
                'posts_per_page' => 100,
                'paged'          => $paged,
                'no_found_rows'  => false,
                'fields'         => 'all',
            ]);

            foreach ($query->posts as $post) {
                /** @var \WP_Post $post */
                $data   = FieldDataService::getInstance()->getAll($post->ID, 'post');
                $row    = [$post->ID, $post->post_title, $post->post_status];

                foreach (array_keys($allFields) as $key) {
                    $value = $data[$key] ?? '';
                    $row[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                }

                yield $this->encodeRow($row);
            }

            $paged++;
        } while ($paged <= $query->max_num_pages);
    }

    /**
     * Streams all CSV rows to a file handle (for large exports without memory spikes).
     *
     * @param resource             $handle
     * @param string[]             $fieldKeys
     */
    public function exportToHandle(
        $handle,
        string $postType,
        array  $fieldKeys = [],
        string $postStatus = 'any',
    ): void {
        foreach ($this->export($postType, $fieldKeys, $postStatus) as $row) {
            fwrite($handle, $row);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * @return array<string, true>  key → true (ordered)
     */
    private function collectFields(array $groups, array $filterKeys): array
    {
        $map = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $key = $field->getKey();

                if (! empty($filterKeys) && ! in_array($key, $filterKeys, true)) {
                    continue;
                }

                $map[$key] = true;
            }
        }

        return $map;
    }

    /** @return FieldGroup[] */
    private function resolveGroups(string $postType): array
    {
        $groups = [];

        foreach (FieldRegistry::all() as $group) {
            foreach ($group->getAndConditions() as $condition) {
                if (
                    $condition['key'] === 'post_type'
                    && $condition['operator'] === '=='
                    && $condition['value'] === $postType
                ) {
                    $groups[] = $group;
                    break;
                }
            }
        }

        return $groups;
    }

    /** @param array<int, mixed> $row */
    private function encodeRow(array $row): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_map('strval', $row), ',', '"', '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);
        return $line;
    }
}
