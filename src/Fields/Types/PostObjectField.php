<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\FieldDefinition;

final class PostObjectField extends FieldDefinition implements FieldSanitizerInterface, CollectionConstraintsInterface
{
    /** @var array<int, string> */
    private array   $postTypes    = [];
    private bool    $multiple     = false;
    private ?int    $minPosts     = null;
    private ?int    $maxPosts     = null;
    /** @var array<int, string> */
    private array   $filters      = ['search'];

    // PostObject default return format is 'id' (overrides FieldDefinition's empty default)
    protected string $returnFormat = 'id';

    public function getType(): FieldType
    {
        return FieldType::POST_OBJECT;
    }

    /** @param string|array<int, string> $types */
    public function postType(string|array $types): static
    {
        $this->postTypes = is_array($types) ? array_values($types) : [$types];
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function minPosts(int $min): static
    {
        $this->minPosts = $min;
        return $this;
    }

    public function maxPosts(int $max): static
    {
        $this->maxPosts = $max;
        return $this;
    }

    /** @param array<int, string> $filters */
    public function filters(array $filters): static
    {
        $this->filters = $filters;
        return $this;
    }

    public function returnFormat(string $format): static
    {
        $this->returnFormat = $format;
        return $this;
    }

    // -------------------------------------------------------------------------
    // CollectionConstraintsInterface
    // -------------------------------------------------------------------------

    public function getMinItems(): ?int
    {
        return $this->minPosts;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxPosts;
    }

    // -------------------------------------------------------------------------
    // FieldSanitizerInterface
    // -------------------------------------------------------------------------

    public function sanitizeForStorage(mixed $value): mixed
    {
        if ($this->multiple) {
            $ids = is_array($value) ? $value : [];
            $valid = array_values(array_filter(
                array_map('intval', $ids),
                static fn (int $id) => $id > 0 && get_post($id) !== null,
            ));
            return $valid;
        }

        $id = is_numeric($value) ? (int) $value : 0;

        if ($id <= 0 || get_post($id) === null) {
            return 0;
        }

        return $id;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /** @return array<int, string> */
    public function getPostTypes(): array
    {
        return $this->postTypes;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getReturnFormat(): string
    {
        return $this->returnFormat;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'post_types'    => $this->postTypes,
            'multiple'      => $this->multiple,
            'min_posts'     => $this->minPosts,
            'max_posts'     => $this->maxPosts,
            'filters'       => $this->filters,
            'return_format' => $this->returnFormat,
        ]);
    }
}
