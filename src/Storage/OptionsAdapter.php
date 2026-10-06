<?php

declare(strict_types=1);

namespace CtrlField\Storage;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use CtrlField\Storage\Drivers\OptionsDriverInterface;
use InvalidArgumentException;

class OptionsAdapter implements StorageAdapterInterface
{
    public const KEY_PREFIX         = '_ctrlfield_options_';
    public const AUTOLOAD_THRESHOLD = 50 * 1024; // 50 KB

    public function __construct(
        private readonly OptionsDriverInterface $driver
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $indexedFields Ignored for options storage (no meta_query needed).
     */
    public function save(int|string $id, array $data, int $version, array $indexedFields = []): void
    {
        if (! is_string($id)) {
            throw new InvalidArgumentException('OptionsAdapter requires a string page key.');
        }

        $payload = json_encode(
            ['schema_version' => $version, 'fields' => $data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $autoload = strlen($payload) > self::AUTOLOAD_THRESHOLD ? 'no' : 'yes';

        $this->driver->update(self::KEY_PREFIX . $id, $payload, $autoload);

        CacheAdapter::invalidate("options:{$id}");
    }

    /** @return array<string, mixed>|null */
    public function load(int|string $id): ?array
    {
        if (! is_string($id)) {
            throw new InvalidArgumentException('OptionsAdapter requires a string page key.');
        }

        $cacheKey = "options:{$id}";
        $cached   = CacheAdapter::get($cacheKey);

        if ($cached !== null) {
            return is_array($cached) ? $cached : null;
        }

        $raw = $this->driver->get(self::KEY_PREFIX . $id);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        // Version mismatch check — same policy as PostMetaAdapter: reject stale payloads.
        $storedVersion = isset($decoded['schema_version']) && is_int($decoded['schema_version'])
            ? $decoded['schema_version']
            : 0;

        if ($storedVersion < SchemaVersion::CURRENT) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'CtrlField: schema_version mismatch on options page "%s" (stored: %d, current: %d). Run "wp ctrlfield migrate".',
                    $id,
                    $storedVersion,
                    SchemaVersion::CURRENT,
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
