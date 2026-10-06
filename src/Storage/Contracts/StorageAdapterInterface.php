<?php

declare(strict_types=1);

namespace CtrlField\Storage\Contracts;

interface StorageAdapterInterface
{
    /**
     * Persist field data.
     *
     * @param int|string           $id            Post ID (int) or options page key (string).
     * @param array<string, mixed> $data           All field values to store as JSON blob.
     * @param int                  $version        Schema version written into the payload.
     * @param array<string, mixed> $indexedFields  Fields to also mirror as individual rows for querying.
     */
    public function save(int|string $id, array $data, int $version, array $indexedFields = []): void;

    /**
     * Load field data.
     *
     * @param int|string $id Post ID (int) or options page key (string).
     * @return array<string, mixed>|null Null when no data is stored or payload is unreadable.
     */
    public function load(int|string $id): ?array;
}
