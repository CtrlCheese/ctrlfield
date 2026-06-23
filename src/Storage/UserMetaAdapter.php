<?php

declare(strict_types=1);

namespace FieldForge\Storage;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Storage\Contracts\StorageAdapterInterface;

/**
 * Stores FieldForge field data in wp_usermeta.
 * Same JSON blob format as PostMetaAdapter — schema_version + fields.
 * Cache key pattern: fieldforge:user:{userId}
 */
class UserMetaAdapter implements StorageAdapterInterface
{
    public const META_KEY         = '_fieldforge_data';
    public const INDEX_KEY_PREFIX = '_fieldforge_idx_';

    public function __construct(
        private readonly int $currentVersion = SchemaVersion::CURRENT,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $indexedFields
     */
    public function save(int|string $id, array $data, int $version, array $indexedFields = []): void
    {
        $payload = json_encode(
            ['schema_version' => $version, 'fields' => $data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        if (function_exists('update_user_meta')) {
            update_user_meta((int) $id, self::META_KEY, $payload);

            foreach ($indexedFields as $key => $value) {
                update_user_meta((int) $id, self::INDEX_KEY_PREFIX . $key, $value);
            }
        }

        CacheAdapter::invalidate("user:{$id}");
    }

    /** @return array<string, mixed>|null */
    public function load(int|string $id): ?array
    {
        $cacheKey = "user:{$id}";
        $cached   = CacheAdapter::get($cacheKey);

        if ($cached !== null) {
            return is_array($cached) ? $cached : null;
        }

        if (! function_exists('get_user_meta')) {
            return null;
        }

        $raw = get_user_meta((int) $id, self::META_KEY, true);

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

        $storedVersion = isset($decoded['schema_version']) && is_int($decoded['schema_version'])
            ? $decoded['schema_version']
            : 0;

        if ($storedVersion < $this->currentVersion) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'FieldForge: schema_version mismatch on user %s (stored: %d, current: %d). Run "wp fieldforge migrate".',
                    $id, $storedVersion, $this->currentVersion,
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
}
