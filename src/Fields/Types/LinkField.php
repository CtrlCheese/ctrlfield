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
 *  - type: 'post' | 'term' | 'anchor' | 'internal' | 'external', id and object
 *    (post type or taxonomy) — the URL is re-resolved from the id on read, so
 *    links follow permalink changes;
 *  - style: one of ->styles() (buttons);
 *  - icon, icon_position: with ->icons() ("lucide:arrow-right", 'left'|'right').
 * With ->multiple() the value is a list of those links (buttons: max 2 by default).
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

    /** @var list<string> Style of a new link by position: [first, second, …]. */
    private array $defaultStyles = [];

    private bool $multiple = false;

    private int $max = 2;

    /** null = the site default (filter ctrlfield/link/icons, off unless a theme turns it on). */
    private ?bool $icons = null;

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

    /**
     * Style a new link gets by its position — ['primary', 'outline']: the
     * first button primary, the second outline. Without it: the first style.
     *
     * @param list<string> $styles
     */
    public function defaultStyles(array $styles): static
    {
        $this->defaultStyles = array_values(array_filter(
            array_map(static fn ($s) => strtolower(trim((string) $s)), $styles),
            static fn (string $s) => preg_match('/^[a-z0-9_-]{1,32}$/', $s) === 1
        ));
        return $this;
    }

    /** @return list<string> Only styles that ->styles() offers. */
    public function getDefaultStyles(): array
    {
        return array_values(array_filter($this->defaultStyles, fn (string $s) => isset($this->styles[$s])));
    }

    /** Several links in one field (a row of buttons), reordered by dragging. */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /** Most links a multiple field takes (default 2). */
    public function max(int $max): static
    {
        $this->max = max(1, $max);
        return $this;
    }

    /** Same as ->max() (the name JSON groups and repeaters use). */
    public function maxItems(int $max): static
    {
        return $this->max($max);
    }

    public function getMax(): int
    {
        return $this->max;
    }

    /** An icon (and its side) per link, picked in the icon dialog. */
    public function icons(bool $icons = true): static
    {
        $this->icons = $icons;
        return $this;
    }

    /**
     * Whether the dialog offers an icon. Off unless the field turns it on, or
     * a theme turns it on for every link field:
     *   add_filter('ctrlfield/link/icons', fn () => (bool) get_field('buttonIcons', 'option'));
     */
    public function hasIcons(): bool
    {
        if ($this->icons !== null) {
            return $this->icons;
        }

        return function_exists('apply_filters') ? (bool) apply_filters('ctrlfield/link/icons', false, $this) : false;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'multiple'       => $this->multiple,
            'max'            => $this->multiple ? $this->max : 1,
            'default_styles' => $this->getDefaultStyles(),
            'icons'          => $this->icons,
            'show_target' => $this->showTarget,
            'post_types'  => $this->postTypes,
            'taxonomies'  => $this->taxonomies,
            'anchors'     => $this->anchors,
            'styles'      => $this->styles,
        ]);
    }
}
