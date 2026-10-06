<?php

declare(strict_types=1);

namespace CtrlField\Builder;

/**
 * Fluent builder for Custom Post Type registration.
 *
 * Calling register() stores the definition in a static list.
 * The actual register_post_type() WP call is wired via WordPressServiceProvider on init.
 */
final class CPT
{
    private string      $singularLabel    = '';
    private string      $pluralLabel      = '';
    private string      $menuIcon         = 'dashicons-admin-post';
    private bool        $showInRest       = true;
    private bool|string $hasArchive       = false;
    private string      $rewriteSlug      = '';
    private ?int        $menuPosition     = null;
    private string      $description      = '';
    private bool        $isPublic         = true;
    private string      $capabilityType   = 'post';

    // Parameters missing from the original implementation
    private bool        $isHierarchical   = false;
    private bool        $excludeFromSearch = false;
    private bool        $publiclyQueryable = true;
    private bool|string $showInMenu       = true;
    private bool        $showInAdminBar   = true;
    private bool        $canExport        = true;
    private bool        $showUi           = true;

    /** @var array<int, string> */
    private array $supports = ['title', 'editor'];

    /** @var array<string, self> */
    private static array $registry = [];

    private function __construct(private readonly string $postType) {}

    public static function make(string $postType): self
    {
        return new self($postType);
    }

    public function label(string $singular, string $plural): self
    {
        $this->singularLabel = $singular;
        $this->pluralLabel   = $plural;
        return $this;
    }

    public function menuIcon(string $icon): self
    {
        $this->menuIcon = $icon;
        return $this;
    }

    /** @param array<int, string> $features */
    public function supports(array $features): self
    {
        $this->supports = $features;
        return $this;
    }

    public function showInRest(bool $show = true): self
    {
        $this->showInRest = $show;
        return $this;
    }

    public function hasArchive(bool|string $archive = true): self
    {
        $this->hasArchive = $archive;
        return $this;
    }

    public function rewriteSlug(string $slug): self
    {
        $this->rewriteSlug = $slug;
        return $this;
    }

    public function menuPosition(int $position): self
    {
        $this->menuPosition = $position;
        return $this;
    }

    public function description(string $text): self
    {
        $this->description = $text;
        return $this;
    }

    public function public(bool $public = true): self
    {
        $this->isPublic = $public;
        return $this;
    }

    public function capability(string $type = 'post'): self
    {
        $this->capabilityType = $type;
        return $this;
    }

    /** Whether posts of this type can be parents of other posts (page-like). */
    public function hierarchical(bool $hierarchical = true): self
    {
        $this->isHierarchical = $hierarchical;
        return $this;
    }

    /** Exclude from the front-end search results. */
    public function excludeFromSearch(bool $exclude = true): self
    {
        $this->excludeFromSearch = $exclude;
        return $this;
    }

    /**
     * Whether queries can be performed on the front end as part of parse_request().
     * Set to false for admin-only CPTs like orders, logs, etc.
     */
    public function publiclyQueryable(bool $queryable = true): self
    {
        $this->publiclyQueryable = $queryable;
        return $this;
    }

    /**
     * Where to show the post type in the admin menu.
     * true = show under Posts; false = do not show in menu;
     * string = show as a sub-menu of the given top-level menu slug.
     */
    public function showInMenu(bool|string $show = true): self
    {
        $this->showInMenu = $show;
        return $this;
    }

    /** Whether to make the post type available in the WordPress toolbar. */
    public function showInAdminBar(bool $show = true): self
    {
        $this->showInAdminBar = $show;
        return $this;
    }

    /** Whether to allow this post type to be exported via Tools > Export. */
    public function canExport(bool $can = true): self
    {
        $this->canExport = $can;
        return $this;
    }

    /** Whether to generate and allow a UI for managing this post type in the admin. */
    public function showUi(bool $show = true): self
    {
        $this->showUi = $show;
        return $this;
    }

    public function register(): void
    {
        self::$registry[$this->postType] = $this;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getPostType(): string       { return $this->postType; }
    public function getSingularLabel(): string  { return $this->singularLabel; }
    public function getPluralLabel(): string    { return $this->pluralLabel; }
    public function getMenuIcon(): string       { return $this->menuIcon; }
    public function getSupports(): array        { return $this->supports; }
    public function isShowInRest(): bool        { return $this->showInRest; }
    public function getHasArchive(): bool|string { return $this->hasArchive; }
    public function getRewriteSlug(): string    { return $this->rewriteSlug; }
    public function getMenuPosition(): ?int     { return $this->menuPosition; }
    public function getDescription(): string    { return $this->description; }
    public function isPublic(): bool            { return $this->isPublic; }
    public function getCapabilityType(): string { return $this->capabilityType; }
    public function isHierarchical(): bool      { return $this->isHierarchical; }
    public function isExcludeFromSearch(): bool { return $this->excludeFromSearch; }
    public function isPubliclyQueryable(): bool { return $this->publiclyQueryable; }
    public function getShowInMenu(): bool|string { return $this->showInMenu; }
    public function isShowInAdminBar(): bool    { return $this->showInAdminBar; }
    public function canExportPosts(): bool      { return $this->canExport; }
    public function isShowUi(): bool            { return $this->showUi; }

    // -------------------------------------------------------------------------
    // Static registry helpers
    // -------------------------------------------------------------------------

    /** @return array<string, self> */
    public static function all(): array { return self::$registry; }
    public static function has(string $postType): bool { return isset(self::$registry[$postType]); }
    public static function reset(): void { self::$registry = []; }
}
