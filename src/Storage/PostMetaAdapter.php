<?php

declare(strict_types=1);

namespace CtrlField\Storage;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use CtrlField\Storage\Drivers\PostMetaDriverInterface;
use InvalidArgumentException;

class PostMetaAdapter implements StorageAdapterInterface
{
    public const META_KEY         = '_ctrlfield_data';
    public const INDEX_KEY_PREFIX = '_ctrlfield_idx_';

    public function __construct(
        private readonly PostMetaDriverInterface $driver,
        private readonly int $currentVersion = SchemaVersion::CURRENT,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $indexedFields
     */
    public function save(int|string $id, array $data, int $version, array $indexedFields = []): void
    {
        if (! is_int($id)) {
            throw new InvalidArgumentException('PostMetaAdapter requires an integer post ID.');
        }

        $payload = json_encode(
            ['schema_version' => $version, 'fields' => $data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $this->backupUnreadable($id);
        $this->driver->update($id, self::META_KEY, $payload);

        foreach ($indexedFields as $key => $value) {
            $this->driver->update($id, self::INDEX_KEY_PREFIX . $key, $value);
        }

        CacheAdapter::invalidate("post:{$id}");
    }

    public const BACKUP_KEY = '_ctrlfield_data_backup';

    /**
     * Stored data that load() cannot read (corrupt JSON, older schema version)
     * would be overwritten by this save. Keep a copy instead of losing it.
     */
    private function backupUnreadable(int $id): void
    {
        $raw = $this->driver->get($id, self::META_KEY);

        if (! is_string($raw) || $raw === '') {
            return;
        }

        $decoded  = json_decode($raw, true);
        $readable = is_array($decoded)
            && isset($decoded['fields'])
            && (int) ($decoded['schema_version'] ?? 0) >= $this->currentVersion;

        if (! $readable) {
            $this->driver->update($id, self::BACKUP_KEY, $raw);
        }
    }

    /**
     * Load field data, rejecting payloads with an outdated schema version.
     *
     * Returns null when:
     * - No data is stored for this post.
     * - The stored schema_version is less than $this->currentVersion
     *   (data must be migrated first via `wp ctrlfield migrate`).
     * - The stored JSON is malformed or missing the `fields` key.
     *
     * @return array<string, mixed>|null
     */
    public function load(int|string $id): ?array
    {
        if (! is_int($id)) {
            throw new InvalidArgumentException('PostMetaAdapter requires an integer post ID.');
        }

        $cacheKey = "post:{$id}";
        $cached   = CacheAdapter::get($cacheKey);

        if ($cached !== null) {
            return is_array($cached) ? $cached : null;
        }

        $raw = $this->driver->get($id, self::META_KEY);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        // Version mismatch check — reject stale payloads, never silently migrate at runtime.
        $storedVersion = isset($decoded['schema_version']) && is_int($decoded['schema_version'])
            ? $decoded['schema_version']
            : 0;

        if ($storedVersion < $this->currentVersion) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'CtrlField: schema_version mismatch on post %d (stored: %d, current: %d). Run "wp ctrlfield migrate".',
                    $id,
                    $storedVersion,
                    $this->currentVersion,
                ));
            }
            return null;
        }

        $fields = isset($decoded['fields']) && is_array($decoded['fields'])
            ? $decoded['fields']
            : null;

        if ($fields !== null) {
            CacheAdapter::set($cacheKey, $fields);
        }

        return $fields;
    }

    /**
     * Convenience method: load a single field value for a post.
     *
     * Uses the WP Object Cache — no extra DB query if the blob was already
     * loaded during this request (e.g. by MetaBoxRenderer).
     */
    public static function loadSingleField(int $postId, string $fieldKey): mixed
    {
        $adapter = new self(new \CtrlField\Storage\Drivers\WpPostMetaDriver());
        return ($adapter->load($postId) ?? [])[$fieldKey] ?? null;
    }

    /**
     * Load the raw payload (schema_version + fields) without version enforcement.
     *
     * Used exclusively by the migration engine to inspect and upgrade stale records.
     *
     * @return array{schema_version: int, fields: array<string, mixed>}|null
     */
    public function loadRaw(int $id): ?array
    {
        $raw = $this->driver->get($id, self::META_KEY);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'schema_version' => isset($decoded['schema_version']) && is_int($decoded['schema_version'])
                ? $decoded['schema_version']
                : 0,
            'fields' => isset($decoded['fields']) && is_array($decoded['fields'])
                ? $decoded['fields']
                : [],
        ];
    }
}
