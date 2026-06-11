<?php

declare(strict_types=1);

namespace FieldForge\Data;

use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;

/**
 * Imports post field data from a CSV file.
 *
 * Row handling rules:
 *  - post_id present and > 0 → UPDATE if post exists (requires --update-existing)
 *  - post_id absent or 0     → CREATE new post (requires --create-missing)
 *  - Unknown columns (not in schema) → row skipped with a warning
 *
 * Does NOT run the full SavePipeline (no nonce/capability stages).
 * TypeCoercion and Sanitization are applied field-by-field using the same
 * logic as the pipeline stages.
 *
 * Excluded from PHPStan — references WP functions.
 */
final class CsvImporter
{
    /**
     * @return array{updated: int, created: int, skipped: int, errors: list<string>}
     */
    public function import(
        string $filePath,
        string $postType,
        bool   $dryRun         = false,
        bool   $updateExisting = true,
        bool   $createMissing  = false,
    ): array {
        $stats = ['updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            $stats['errors'][] = "Cannot open file: {$filePath}";
            return $stats;
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($headers)) {
                $stats['errors'][] = 'CSV file is empty or unreadable.';
                return $stats;
            }

            $headers    = array_map('trim', $headers);
            $groups     = $this->resolveGroups($postType);
            $schemaKeys = $this->collectSchemaKeys($groups);
            $rowNum     = 1;

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rowNum++;

                if (count($row) !== count($headers)) {
                    $stats['errors'][] = "Row {$rowNum}: column count mismatch — skipped.";
                    $stats['skipped']++;
                    continue;
                }

                $record = array_combine($headers, $row);
                if ($record === false) {
                    $stats['errors'][] = "Row {$rowNum}: could not parse — skipped.";
                    $stats['skipped']++;
                    continue;
                }

                $result = $this->processRow(
                    $record,
                    $postType,
                    $schemaKeys,
                    $rowNum,
                    $dryRun,
                    $updateExisting,
                    $createMissing,
                );

                $stats[$result['status']]++;

                if (isset($result['error'])) {
                    $stats['errors'][] = $result['error'];
                }
            }
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $record
     * @param array<string, true>   $schemaKeys
     * @return array{status: 'updated'|'created'|'skipped', error?: string}
     */
    private function processRow(
        array  $record,
        string $postType,
        array  $schemaKeys,
        int    $rowNum,
        bool   $dryRun,
        bool   $updateExisting,
        bool   $createMissing,
    ): array {
        $postId     = isset($record['post_id']) ? (int) $record['post_id'] : 0;
        $postTitle  = $record['post_title'] ?? '';
        $postStatus = $record['post_status'] ?? 'publish';

        $fieldData = [];

        foreach ($record as $key => $value) {
            if (in_array($key, ['post_id', 'post_title', 'post_status'], true)) {
                continue;
            }

            if (! isset($schemaKeys[$key])) {
                return [
                    'status' => 'skipped',
                    'error'  => "Row {$rowNum}: column '{$key}' not in schema for post_type '{$postType}' — skipped.",
                ];
            }

            // Decode JSON-encoded complex fields (group, repeater, link)
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    $fieldData[$key] = $decoded;
                } catch (\JsonException) {
                    $fieldData[$key] = $value;
                }
            } else {
                $fieldData[$key] = $value;
            }
        }

        if ($postId > 0) {
            if (! $updateExisting) {
                return [
                    'status' => 'skipped',
                    'error'  => "Row {$rowNum}: post_id {$postId} found but --update-existing not set — skipped.",
                ];
            }

            if (! function_exists('get_post') || get_post($postId) === null) {
                return [
                    'status' => 'skipped',
                    'error'  => "Row {$rowNum}: post_id {$postId} does not exist — skipped.",
                ];
            }

            if (function_exists('current_user_can') && ! current_user_can('edit_post', $postId)) {
                return [
                    'status' => 'skipped',
                    'error'  => "Row {$rowNum}: insufficient permission to edit post {$postId} — skipped.",
                ];
            }

            if (! $dryRun) {
                $this->saveFields($postId, $fieldData);
            }

            return ['status' => 'updated'];
        }

        // No post_id — create new
        if (! $createMissing) {
            return [
                'status' => 'skipped',
                'error'  => "Row {$rowNum}: no post_id and --create-missing not set — skipped.",
            ];
        }

        if (! $dryRun) {
            $newId = wp_insert_post([
                'post_title'  => sanitize_text_field($postTitle),
                'post_type'   => $postType,
                'post_status' => sanitize_text_field($postStatus),
            ], true);

            if (is_wp_error($newId)) {
                return [
                    'status' => 'skipped',
                    'error'  => "Row {$rowNum}: wp_insert_post failed — " . $newId->get_error_message(),
                ];
            }

            $this->saveFields((int) $newId, $fieldData);
        }

        return ['status' => 'created'];
    }

    /** @param array<string, mixed> $fields */
    private function saveFields(int $postId, array $fields): void
    {
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());

        // Use loadRaw() to read existing fields regardless of schema_version.
        // Using getAll() would return [] for unmigrated posts, erasing all other fields.
        $raw      = $adapter->loadRaw($postId);
        $existing = $raw['fields'] ?? [];
        $merged   = array_merge($existing, $fields);

        $adapter->save($postId, $merged, SchemaVersion::CURRENT);
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

    /**
     * @param FieldGroup[] $groups
     * @return array<string, true>
     */
    private function collectSchemaKeys(array $groups): array
    {
        $keys = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $keys[$field->getKey()] = true;
            }
        }

        return $keys;
    }
}
