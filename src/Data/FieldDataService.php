<?php

declare(strict_types=1);

namespace FieldForge\Data;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Storage\CommentMetaAdapter;
use FieldForge\Storage\Contracts\StorageAdapterInterface;
use FieldForge\Storage\Drivers\WpOptionsDriver;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\OptionsAdapter;
use FieldForge\Storage\PostMetaAdapter;
use FieldForge\Storage\TermMetaAdapter;
use FieldForge\Storage\UserMetaAdapter;

final class FieldDataService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Resets the singleton for testing.
     *
     * NOT for production use — the singleton is intentionally shared across
     * a request. Call this in test setUp/tearDown to prevent cross-test pollution.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Reads a single field value for any entity type, applying returnFormat decorators.
     *
     * @param string     $entityType 'post' | 'user' | 'term' | 'comment' | 'options'
     * @param int|string $entityId   Post/user/term ID, or options page slug.
     */
    public function get(string $key, int|string $entityId, string $entityType = 'post'): mixed
    {
        $raw = ($this->getAll($entityId, $entityType))[$key] ?? null;
        return $this->decorate($raw, $key);
    }

    /**
     * Reads all stored raw field values for an entity.
     *
     * NOTE: returnFormat decorators are NOT applied here — this method returns
     * the raw stored primitives (IDs, strings, arrays). Use getAllDecorated()
     * when you need decorated values, or get() for a single decorated field.
     *
     * @param int|string $entityId   Post/user/term ID, or options page slug.
     * @return array<string, mixed>  Empty array if nothing is stored.
     */
    public function getAll(int|string $entityId, string $entityType = 'post'): array
    {
        $adapter = $this->resolveAdapter($entityType);
        return $adapter->load($entityId) ?? [];
    }

    /**
     * Reads all stored fields and applies returnFormat decorators to each.
     *
     * @param int|string $entityId
     * @return array<string, mixed>
     */
    public function getAllDecorated(int|string $entityId, string $entityType = 'post'): array
    {
        $raw     = $this->getAll($entityId, $entityType);
        $result  = [];

        foreach ($raw as $key => $value) {
            $result[$key] = $this->decorate($value, $key);
        }

        return $result;
    }

    // -------------------------------------------------------------------------

    private function decorate(mixed $value, string $key): mixed
    {
        $field = $this->resolveFieldDefinition($key);

        if ($field === null || $field->getReturnFormat() === '') {
            return $value;
        }

        return ReturnFormatDecorator::apply($value, $field);
    }

    private function resolveFieldDefinition(string $key): ?FieldDefinition
    {
        foreach (FieldRegistry::all() as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getKey() === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function resolveAdapter(string $entityType): StorageAdapterInterface
    {
        return match ($entityType) {
            'user'    => new UserMetaAdapter(),
            'term'    => new TermMetaAdapter(),
            'comment' => new CommentMetaAdapter(),
            'options' => new OptionsAdapter(new WpOptionsDriver()),
            default   => new PostMetaAdapter(new WpPostMetaDriver()),
        };
    }
}
