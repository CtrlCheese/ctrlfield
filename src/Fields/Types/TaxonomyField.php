<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\FieldDefinition;
use InvalidArgumentException;

final class TaxonomyField extends FieldDefinition implements FieldSanitizerInterface, CollectionConstraintsInterface
{
    private string $taxonomy    = '';
    private bool   $multiple    = true;
    private bool   $createTerms = false;
    private ?int   $minTerms    = null;
    private ?int   $maxTerms    = null;
    private string $appearance  = 'select';

    public function getType(): FieldType
    {
        return FieldType::TAXONOMY_TERM;
    }

    public function taxonomy(string $slug): static
    {
        $this->taxonomy = $slug;
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        if (! $multiple && $this->appearance === 'radio') {
            // radio already implies single selection — allow the combination
        }
        $this->multiple = $multiple;
        return $this;
    }

    public function createTerms(bool $allow = true): static
    {
        $this->createTerms = $allow;
        return $this;
    }

    public function minTerms(int $min): static
    {
        $this->minTerms = $min;
        return $this;
    }

    public function maxTerms(int $max): static
    {
        $this->maxTerms = $max;
        return $this;
    }

    public function appearance(string $mode): static
    {
        if (! in_array($mode, ['select', 'checkbox', 'radio'], true)) {
            throw new InvalidArgumentException("Invalid appearance '{$mode}'. Valid values: select, checkbox, radio.");
        }

        // radio + multiple is not supported
        if ($mode === 'radio' && $this->multiple) {
            throw new InvalidArgumentException("appearance('radio') does not support multiple(true). Call multiple(false) first.");
        }

        $this->appearance = $mode;
        return $this;
    }

    // -------------------------------------------------------------------------
    // CollectionConstraintsInterface
    // -------------------------------------------------------------------------

    public function getMinItems(): ?int
    {
        return $this->minTerms;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxTerms;
    }

    // -------------------------------------------------------------------------
    // FieldSanitizerInterface
    // -------------------------------------------------------------------------

    public function sanitizeForStorage(mixed $value): mixed
    {
        $taxonomy = $this->taxonomy;

        if ($this->multiple) {
            // A single id is a one-item list, not "nothing" (it used to be dropped).
            $ids = is_array($value) ? $value : (($value === null || $value === '') ? [] : [$value]);
            return array_values(array_filter(
                array_map(function (mixed $item) use ($taxonomy): int {
                    if (is_string($item) && ! is_numeric($item) && $this->createTerms) {
                        $result = wp_insert_term($item, $taxonomy);
                        return is_wp_error($result) ? 0 : (int) $result['term_id'];
                    }
                    $id = (int) $item;
                    return ($id > 0 && get_term($id, $taxonomy) !== null && ! is_wp_error(get_term($id, $taxonomy))) ? $id : 0;
                }, $ids),
                static fn (int $id) => $id > 0,
            ));
        }

        $id = is_numeric($value) ? (int) $value : 0;

        if ($id <= 0) {
            return 0;
        }

        $term = get_term($id, $taxonomy);

        return ($term !== null && ! is_wp_error($term)) ? $id : 0;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getTaxonomy(): string
    {
        return $this->taxonomy;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getAppearance(): string
    {
        return $this->appearance;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'taxonomy'     => $this->taxonomy,
            'multiple'     => $this->multiple,
            'create_terms' => $this->createTerms,
            'min_terms'    => $this->minTerms,
            'max_terms'    => $this->maxTerms,
            'appearance'   => $this->appearance,
        ]);
    }
}
