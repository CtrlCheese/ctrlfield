<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * A custom post type created in the admin UI (no code).
 *
 * Pure value object: fromInput() normalises and validates raw form input,
 * toArray()/fromArray() round-trip the stored option. No WordPress calls,
 * so it is unit-testable.
 */
final class PostTypeDefinition
{
    public const SUPPORTS = [
        'title', 'editor', 'thumbnail', 'excerpt', 'author',
        'comments', 'revisions', 'page-attributes', 'custom-fields',
    ];

    /** Post type keys WordPress core uses or reserves (register_post_type docs). */
    public const RESERVED = [
        'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css',
        'customize_changeset', 'oembed_cache', 'user_request', 'wp_block',
        'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
        'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme',
    ];

    /**
     * @param string[] $supports
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
            $errors['slug'] = 'The key is required.';
        } elseif (! preg_match('/^[a-z][a-z0-9_-]{0,19}$/', $slug)) {
            $errors['slug'] = 'Use 1–20 lowercase letters, numbers, "-" or "_", starting with a letter.';
        } elseif (in_array($slug, self::RESERVED, true)) {
            $errors['slug'] = "\"{$slug}\" is reserved by WordPress.";
        }

        $singular = self::text($input['singular'] ?? '');
        $plural   = self::text($input['plural'] ?? '');
        if ($singular === '') {
            $errors['singular'] = 'The singular name is required.';
        }
        if ($plural === '') {
            $errors['plural'] = 'The plural name is required.';
        }

        $icon = trim((string) ($input['icon'] ?? ''));
        if ($icon !== '' && ! preg_match('/^dashicons-[a-z0-9-]+$/', $icon)) {
            $errors['icon'] = 'Use a Dashicons class, e.g. dashicons-portfolio.';
        }

        $supports = array_values(array_intersect(self::SUPPORTS, (array) ($input['supports'] ?? [])));

        $rewrite = trim(strtolower(trim((string) ($input['rewrite_slug'] ?? ''))), '/');
        if ($rewrite !== '' && ! preg_match('/^[a-z0-9][a-z0-9\/_-]*$/', $rewrite)) {
            $errors['rewrite_slug'] = 'Use lowercase letters, numbers, "-", "_" or "/".';
        }

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
        ];
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
