<?php

declare(strict_types=1);

namespace CtrlField\Builder;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;
use InvalidArgumentException;

/**
 * Fluent builder for Custom Taxonomy registration.
 *
 * Calling register() stores the definition in a static list.
 * The actual register_taxonomy() WP call is wired in Cycle 6.
 */
final class Taxonomy
{
    private string $singularLabel   = '';
    private string $pluralLabel     = '';
    private bool   $isHierarchical  = false;
    private bool   $showInRest      = true;
    private string $rewriteSlug     = '';
    private bool   $showInNavMenus  = true;
    private bool   $showTagCloud    = true;
    private string $description     = '';

    // Parameters missing from the original implementation
    private bool   $isPublic        = true;
    private bool   $showAdminColumn = false;

    /** @var array<int, string> */
    private array $attachTo = [];

    /** @var array<int, \CtrlField\Fields\FieldDefinition> */
    private array $termFields = [];

    /** @var array<string, self> */
    private static array $registry = [];

    private function __construct(private readonly string $taxonomy) {}

    public static function make(string $taxonomy): self
    {
        return new self($taxonomy);
    }

    public function label(string $singular, string $plural): self
    {
        $this->singularLabel = $singular;
        $this->pluralLabel   = $plural;
        return $this;
    }

    /** @param array<int, string> $postTypes */
    public function attachTo(array $postTypes): self
    {
        $this->attachTo = $postTypes;
        return $this;
    }

    public function hierarchical(bool $hierarchical = true): self
    {
        $this->isHierarchical = $hierarchical;
        return $this;
    }

    public function showInRest(bool $show = true): self
    {
        $this->showInRest = $show;
        return $this;
    }

    public function rewriteSlug(string $slug): self
    {
        $this->rewriteSlug = $slug;
        return $this;
    }

    public function showInNavMenus(bool $show = true): self
    {
        $this->showInNavMenus = $show;
        return $this;
    }

    public function showTagCloud(bool $show = true): self
    {
        $this->showTagCloud = $show;
        return $this;
    }

    public function description(string $text): self
    {
        $this->description = $text;
        return $this;
    }

    /** Whether the taxonomy should be publicly queryable on the front end. */
    public function public(bool $public = true): self
    {
        $this->isPublic = $public;
        return $this;
    }

    /** Show a column for this taxonomy on the post list table (admin). */
    public function showAdminColumn(bool $show = true): self
    {
        $this->showAdminColumn = $show;
        return $this;
    }

    /**
     * Registers term-level fields for this taxonomy.
     *
     * Limited to text, image, and select in v1.
     * Data stored in term meta under _ctrlfield_term_{key}.
     *
     * @param array<int, FieldDefinition> $fields
     */
    public function termFields(array $fields): self
    {
        $allowed = [FieldType::TEXT, FieldType::IMAGE, FieldType::SELECT];

        foreach ($fields as $field) {
            if (! in_array($field->getType(), $allowed, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        "Taxonomy term fields are limited to text, image, and select in v1. Field '%s' uses type '%s'.",
                        $field->getKey(),
                        $field->getType()->value,
                    )
                );
            }
        }

        $this->termFields = $fields;
        return $this;
    }

    public function register(): void
    {
        self::$registry[$this->taxonomy] = $this;
    }

    // -------------------------------------------------------------------------
    // Accessors (used by service provider in Cycle 6)
    // -------------------------------------------------------------------------

    public function getTaxonomy(): string
    {
        return $this->taxonomy;
    }

    public function getSingularLabel(): string
    {
        return $this->singularLabel;
    }

    public function getPluralLabel(): string
    {
        return $this->pluralLabel;
    }

    public function isHierarchical(): bool
    {
        return $this->isHierarchical;
    }

    /** @return array<int, string> */
    public function getAttachTo(): array
    {
        return $this->attachTo;
    }

    /** @return array<int, FieldDefinition> */
    public function getTermFields(): array
    {
        return $this->termFields;
    }

    public function isShowInRest(): bool
    {
        return $this->showInRest;
    }

    public function getRewriteSlug(): string
    {
        return $this->rewriteSlug;
    }

    public function isShowInNavMenus(): bool
    {
        return $this->showInNavMenus;
    }

    public function isShowTagCloud(): bool
    {
        return $this->showTagCloud;
    }

    public function getDescription(): string    { return $this->description; }
    public function isPublic(): bool            { return $this->isPublic; }
    public function isShowAdminColumn(): bool   { return $this->showAdminColumn; }

    // -------------------------------------------------------------------------
    // Static registry helpers
    // -------------------------------------------------------------------------

    /** @return array<string, self> */
    public static function all(): array
    {
        return self::$registry;
    }

    public static function has(string $taxonomy): bool
    {
        return isset(self::$registry[$taxonomy]);
    }

    public static function reset(): void
    {
        self::$registry = [];
    }
}
