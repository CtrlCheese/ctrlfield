<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * A link chosen in a WordPress-style link dialog: a URL typed by hand, a post
 * or term found by search (tabs per post type / taxonomy), or an anchor on the
 * page.
 *
 * Stored as { url, title, target } plus, when they apply:
 *  - type: 'post' | 'term' | 'anchor' | 'external', id and object (post type
 *    or taxonomy) — the URL is re-resolved from the id on read, so links follow
 *    permalink changes;
 *  - style: one of ->styles() (buttons).
 * NOT a GroupField — the sub-values are fixed, not user-defined.
 */
final class LinkField extends FieldDefinition
{
    private bool $showTarget = true;

    /** @var list<string> Post types offered as tabs; empty = every public post type. */
    private array $postTypes = [];

    /** @var list<string> Taxonomies offered as tabs (opt-in). */
    private array $taxonomies = [];

    private bool $anchors = true;

    /** @var array<string, string> value => label */
    private array $styles = [];

    public function getType(): FieldType
    {
        return FieldType::LINK;
    }

    /** Whether to show the "open in new tab" toggle in the admin UI. */
    public function showTarget(bool $show = true): static
    {
        $this->showTarget = $show;
        return $this;
    }

    public function getShowTarget(): bool
    {
        return $this->showTarget;
    }

    /** @param string|list<string> $types Post types to search; empty = every public post type. */
    public function postType(string|array $types): static
    {
        $this->postTypes = array_values(array_filter((array) $types, 'is_string'));
        return $this;
    }

    /** @return list<string> */
    public function getPostTypes(): array
    {
        return $this->postTypes;
    }

    /** @param string|list<string> $taxonomies Taxonomies whose terms can be linked. */
    public function taxonomies(string|array $taxonomies): static
    {
        $this->taxonomies = array_values(array_filter((array) $taxonomies, 'is_string'));
        return $this;
    }

    /** @return list<string> */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /** Offer the anchors (#id) found on the page being edited. */
    public function anchors(bool $anchors = true): static
    {
        $this->anchors = $anchors;
        return $this;
    }

    public function getAnchors(): bool
    {
        return $this->anchors;
    }

    /**
     * Button styles the editor picks from, e.g. ['primary' => 'Primary', 'outline' => 'Outline'].
     * The first one is the default.
     *
     * @param array<string, string> $styles
     */
    public function styles(array $styles): static
    {
        $clean = [];
        foreach ($styles as $value => $label) {
            $value = strtolower(trim((string) $value));
            if (preg_match('/^[a-z0-9_-]{1,32}$/', $value)) {
                $clean[$value] = (string) $label;
            }
        }
        $this->styles = $clean;
        return $this;
    }

    /** @return array<string, string> */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'show_target' => $this->showTarget,
            'post_types'  => $this->postTypes,
            'taxonomies'  => $this->taxonomies,
            'anchors'     => $this->anchors,
            'styles'      => $this->styles,
        ]);
    }
}
