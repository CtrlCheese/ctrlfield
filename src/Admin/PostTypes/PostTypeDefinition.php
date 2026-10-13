<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * A custom post type created in the admin UI (no code).
 *
 * Pure value object: fromInput() normalises and validates raw form input,
 * toArray()/fromArray() round-trip the stored option. Only __() from
 * WordPress, so it stays unit-testable.
 */
final class PostTypeDefinition
{
    public const SUPPORTS = [
        'title', 'editor', 'thumbnail', 'excerpt', 'author',
        'comments', 'revisions', 'page-attributes', 'custom-fields',
    ];

    /** Post type keys WordPress core uses (and ReservedNames::TERMS, its query vars). */
    public const RESERVED = ReservedNames::POST_TYPES;

    public const CAPABILITY_TYPES = ['post', 'page'];

    /**
     * @param string[] $supports
     * @param string[] $taxonomies taxonomies used with this post type
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $singular,
        public readonly string $plural,
        public readonly string $icon = 'dashicons-admin-post',
        public readonly array $supports = ['title', 'editor'],
        public readonly bool $public = true,
        public readonly bool $hierarchical = false,
        public readonly bool $hasArchive = true,
        public readonly bool $showInRest = true,
        public readonly string $rewriteSlug = '',
        public readonly int $menuPosition = 25,
        public readonly string $description = '',
        public readonly array $taxonomies = [],
        public readonly string $archiveSlug = '',
        public readonly string $restBase = '',
        public readonly bool $excludeFromSearch = false,
        public readonly string $capabilityType = 'post',
    ) {}

    /**
     * Normalise raw form input. Returns the definition, or the list of
     * validation errors keyed by field.
     *
     * @param array<string, mixed> $input
     * @return array{0: ?self, 1: array<string, string>}
     */
    public static function fromInput(array $input): array
    {
        $errors = [];

        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $errors['slug'] = __('The key is required.', 'ctrlfield');
        } elseif (! preg_match('/^[a-z][a-z0-9_-]{0,19}$/', $slug)) {
            $errors['slug'] = __('Use 1–20 lowercase letters, numbers, "-" or "_", starting with a letter.', 'ctrlfield');
        } elseif (ReservedNames::forPostType($slug)) {
            /* translators: %s: post type key */
            $errors['slug'] = sprintf(__('"%s" is reserved by WordPress.', 'ctrlfield'), $slug);
        }

        $singular = self::text($input['singular'] ?? '');
        $plural   = self::text($input['plural'] ?? '');
        if ($singular === '') {
            $errors['singular'] = __('The singular name is required.', 'ctrlfield');
        }
        if ($plural === '') {
            $errors['plural'] = __('The plural name is required.', 'ctrlfield');
        }

        $icon = trim((string) ($input['icon'] ?? ''));
        if ($icon !== '' && ! preg_match('/^dashicons-[a-z0-9-]+$/', $icon)) {
            $errors['icon'] = __('Use a Dashicons class, e.g. dashicons-portfolio.', 'ctrlfield');
        }

        $supports = array_values(array_intersect(self::SUPPORTS, (array) ($input['supports'] ?? [])));

        $rewrite = self::path($input['rewrite_slug'] ?? '');
        if ($rewrite !== '' && ! preg_match('/^[a-z0-9][a-z0-9\/_-]*$/', $rewrite)) {
            $errors['rewrite_slug'] = __('Use lowercase letters, numbers, "-", "_" or "/".', 'ctrlfield');
        }
        $archive = self::path($input['archive_slug'] ?? '');
        if ($archive !== '' && ! preg_match('/^[a-z0-9][a-z0-9\/_-]*$/', $archive)) {
            $errors['archive_slug'] = __('Use lowercase letters, numbers, "-", "_" or "/".', 'ctrlfield');
        }
        $restBase = strtolower(trim((string) ($input['rest_base'] ?? '')));
        if ($restBase !== '' && ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $restBase)) {
            $errors['rest_base'] = __('Use lowercase letters, numbers, "-" or "_".', 'ctrlfield');
        }
        $taxonomies = array_values(array_unique(array_filter(
            array_map(static fn ($t): string => strtolower((string) $t), (array) ($input['taxonomies'] ?? [])),
            static fn (string $t): bool => (bool) preg_match('/^[a-z0-9_-]{1,32}$/', $t)
        )));
        $capability = (string) ($input['capability_type'] ?? 'post');
        $capability = in_array($capability, self::CAPABILITY_TYPES, true) ? $capability : 'post';

        $position = (int) ($input['menu_position'] ?? 25);
        $position = max(5, min(100, $position));

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new self(
            slug:         $slug,
            singular:     $singular,
            plural:       $plural,
            icon:         $icon !== '' ? $icon : 'dashicons-admin-post',
            supports:     $supports,
            public:       self::bool($input['public'] ?? false),
            hierarchical: self::bool($input['hierarchical'] ?? false),
            hasArchive:   self::bool($input['has_archive'] ?? false),
            showInRest:   self::bool($input['show_in_rest'] ?? false),
            rewriteSlug:  $rewrite,
            menuPosition: $position,
            description:  self::text($input['description'] ?? ''),
            taxonomies:   $taxonomies,
            archiveSlug:  $archive,
            restBase:     $restBase,
            excludeFromSearch: self::bool($input['exclude_from_search'] ?? false),
            capabilityType: $capability,
        ), []];
    }

    /** @param array<string, mixed> $data stored option row */
    public static function fromArray(array $data): self
    {
        return new self(
            slug:         (string) ($data['slug'] ?? ''),
            singular:     (string) ($data['singular'] ?? ''),
            plural:       (string) ($data['plural'] ?? ''),
            icon:         (string) ($data['icon'] ?? 'dashicons-admin-post'),
            supports:     array_values(array_intersect(self::SUPPORTS, (array) ($data['supports'] ?? []))),
            public:       (bool) ($data['public'] ?? true),
            hierarchical: (bool) ($data['hierarchical'] ?? false),
            hasArchive:   (bool) ($data['has_archive'] ?? true),
            showInRest:   (bool) ($data['show_in_rest'] ?? true),
            rewriteSlug:  (string) ($data['rewrite_slug'] ?? ''),
            menuPosition: (int) ($data['menu_position'] ?? 25),
            description:  (string) ($data['description'] ?? ''),
            taxonomies:   array_values(array_map('strval', (array) ($data['taxonomies'] ?? []))),
            archiveSlug:  (string) ($data['archive_slug'] ?? ''),
            restBase:     (string) ($data['rest_base'] ?? ''),
            // Rows saved before this setting: private types were left out of search.
            excludeFromSearch: (bool) ($data['exclude_from_search'] ?? ! (bool) ($data['public'] ?? true)),
            capabilityType: in_array($data['capability_type'] ?? 'post', self::CAPABILITY_TYPES, true) ? (string) ($data['capability_type'] ?? 'post') : 'post',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slug'          => $this->slug,
            'singular'      => $this->singular,
            'plural'        => $this->plural,
            'icon'          => $this->icon,
            'supports'      => $this->supports,
            'public'        => $this->public,
            'hierarchical'  => $this->hierarchical,
            'has_archive'   => $this->hasArchive,
            'show_in_rest'  => $this->showInRest,
            'rewrite_slug'  => $this->rewriteSlug,
            'menu_position' => $this->menuPosition,
            'description'   => $this->description,
            'taxonomies'    => $this->taxonomies,
            'archive_slug'  => $this->archiveSlug,
            'rest_base'     => $this->restBase,
            'exclude_from_search' => $this->excludeFromSearch,
            'capability_type'     => $this->capabilityType,
        ];
    }

    /** has_archive: the archive's own slug, or true (archive at the URL slug), or false. */
    public function archive(): bool|string
    {
        return $this->hasArchive ? ($this->archiveSlug !== '' ? $this->archiveSlug : true) : false;
    }

    /** "/work/" → "work" */
    private static function path(mixed $value): string
    {
        return trim(strtolower(trim((string) $value)), '/');
    }

    private static function text(mixed $value): string
    {
        // Plain text only: labels end up in admin menus and titles.
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    }

    private static function bool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }
}
