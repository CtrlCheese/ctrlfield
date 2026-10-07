<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * Pick a page (or any post type). Stores post IDs; returns permalinks by
 * default, like ACF's Page Link. ->returnFormat('id') returns the IDs.
 */
final class PageLinkField extends FieldDefinition implements FieldSanitizerInterface
{
    /** @var string[] */
    private array $postTypes = ['page'];
    private bool  $multiple  = false;

    public function __construct(string $key)
    {
        parent::__construct($key);
        $this->returnFormat = 'url';
    }

    public function getType(): FieldType
    {
        return FieldType::PAGE_LINK;
    }

    /** @param string|string[] $types */
    public function postType(string|array $types): static
    {
        $this->postTypes = array_values((array) $types);
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    /** @return string[] */
    public function getPostTypes(): array
    {
        return $this->postTypes;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /** @return int|list<int> */
    public function sanitizeForStorage(mixed $value): mixed
    {
        $ids = array_values(array_filter(
            array_map('intval', is_array($value) ? $value : [$value]),
            // Must exist and be one of the field's post types.
            fn (int $id) => $id > 0 && (! function_exists('get_post_type') || in_array(get_post_type($id), $this->postTypes, true)),
        ));

        return $this->multiple ? $ids : ($ids[0] ?? 0);
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'post_type' => $this->postTypes,
            'multiple'  => $this->multiple,
        ]);
    }
}
