<?php

declare(strict_types=1);

namespace FieldForge\Builder;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Registry\FieldRegistry;

/**
 * Fluent builder for options pages (global theme/plugin settings).
 *
 * Calling register() stores the page definition and auto-creates
 * a FieldGroup bound to this page's context key so fields are
 * discoverable via ContextRegistry::resolve().
 *
 * The actual add_menu_page() WP call is wired in Cycle 6.
 * Storage uses _fieldforge_options_{key} in wp_options (Cycle 3).
 */
final class OptionsPage
{
    private string $title      = '';
    private string $menuSlug   = '';
    private string $capability = 'manage_options';
    private string $parent     = '';
    private string $icon       = 'dashicons-admin-generic';
    private int    $position   = 80;

    /** @var array<int, FieldDefinition> */
    private array $fields = [];

    /** @var array<string, self> */
    private static array $registry = [];

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function menuSlug(string $slug): self
    {
        $this->menuSlug = $slug;
        return $this;
    }

    public function capability(string $capability): self
    {
        $this->capability = $capability;
        return $this;
    }

    public function parent(string $parentSlug): self
    {
        $this->parent = $parentSlug;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function position(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    /** @param array<int, FieldDefinition> $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Stores the page in the registry and auto-registers a FieldGroup
     * for any fields bound to this options page.
     */
    public function register(): void
    {
        self::$registry[$this->key] = $this;

        $groupKey = "_options_{$this->key}";

        if (! empty($this->fields) && ! FieldRegistry::has($groupKey)) {
            FieldGroup::make($groupKey)
                ->title($this->title)
                ->where('options_page', '==', $this->key)
                ->fields($this->fields)
                ->register();
        }
    }

    // -------------------------------------------------------------------------
    // Accessors (used by service provider in Cycle 6)
    // -------------------------------------------------------------------------

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMenuSlug(): string
    {
        return $this->menuSlug;
    }

    public function getCapability(): string
    {
        return $this->capability;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** @return array<int, FieldDefinition> */
    public function getFields(): array
    {
        return $this->fields;
    }

    // -------------------------------------------------------------------------
    // Static registry helpers
    // -------------------------------------------------------------------------

    /** @return array<string, self> */
    public static function all(): array
    {
        return self::$registry;
    }

    public static function has(string $key): bool
    {
        return isset(self::$registry[$key]);
    }

    public static function reset(): void
    {
        self::$registry = [];
    }
}
