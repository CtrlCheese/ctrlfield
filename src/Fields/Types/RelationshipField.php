<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Fields\Contracts\ExternalStorageInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Storage\RelationshipAdapter;

final class RelationshipField extends FieldDefinition implements ExternalStorageInterface, CollectionConstraintsInterface
{
    private string  $relatedPostType = '';
    private bool    $multiple        = true;
    private bool    $bidirectional   = false;
    private ?string $pivotTable      = null;
    private ?int    $minItems        = null;
    private ?int    $maxItems        = null;

    public function getType(): FieldType
    {
        return FieldType::RELATIONSHIP;
    }

    public function postType(string $type): static
    {
        $this->relatedPostType = $type;
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function bidirectional(bool $bi = true): static
    {
        $this->bidirectional = $bi;
        return $this;
    }

    public function pivotTable(string $suffix): static
    {
        // Becomes part of SQL (table names cannot be bound as placeholders).
        if (! preg_match('/^[A-Za-z0-9_]{1,48}$/', $suffix)) {
            throw new \InvalidArgumentException("Invalid pivot table name '{$suffix}': use letters, digits and underscores.");
        }
        $this->pivotTable = $suffix;
        return $this;
    }

    public function minItems(int $min): static
    {
        $this->minItems = $min;
        return $this;
    }

    public function maxItems(int $max): static
    {
        $this->maxItems = $max;
        return $this;
    }

    // -------------------------------------------------------------------------
    // CollectionConstraintsInterface
    // -------------------------------------------------------------------------

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    // -------------------------------------------------------------------------
    // ExternalStorageInterface
    // -------------------------------------------------------------------------

    public function persistExternal(int $postId, mixed $value): void
    {
        $targetIds = is_array($value)
            ? array_values(array_map('intval', array_filter($value, 'is_numeric')))
            : [];

        // Only existing posts of the related post type; never the post itself.
        $targetIds = array_values(array_unique(array_filter($targetIds, function (int $id) use ($postId): bool {
            if ($id <= 0 || $id === $postId || ! function_exists('get_post_type')) {
                return $id > 0 && $id !== $postId;
            }
            $type = get_post_type($id);
            return $type !== false && ($this->relatedPostType === '' || $type === $this->relatedPostType);
        })));

        $table = $this->getTableName();
        $key   = $this->getKey();

        RelationshipAdapter::save($postId, $key, $targetIds, $table);

        if ($this->bidirectional) {
            foreach ($targetIds as $targetId) {
                // Save reverse direction: target points back to source.
                $existing = RelationshipAdapter::load($targetId, $key, $table);
                if (! in_array($postId, $existing, true)) {
                    RelationshipAdapter::save($targetId, $key, array_merge($existing, [$postId]), $table);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getTableName(): string
    {
        global $wpdb;

        $suffix = $this->pivotTable ?? ('ctrlf_rel_' . $this->getKey());
        return $wpdb->prefix . $suffix;
    }

    public function getRelatedPostType(): string
    {
        return $this->relatedPostType;
    }

    public function isBidirectional(): bool
    {
        return $this->bidirectional;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'related_post_type' => $this->relatedPostType,
            'multiple'          => $this->multiple,
            'bidirectional'     => $this->bidirectional,
            'min_items'         => $this->minItems,
            'max_items'         => $this->maxItems,
        ]);
    }
}
