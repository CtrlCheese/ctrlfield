<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * A taxonomy created in the admin UI (no code): categories or tags for
 * one or more post types.
 *
 * Pure value object like PostTypeDefinition: fromInput() normalises and
 * validates raw form input, toArray()/fromArray() round-trip the option.
 */
final class TaxonomyDefinition
{
    /**
     * @param string[] $postTypes post types the taxonomy is used with
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $singular,
        public readonly string $plural,
        public readonly array $postTypes = [],
        public readonly bool $hierarchical = true,
        public readonly bool $public = true,
        public readonly bool $showInRest = true,
        public readonly bool $showAdminColumn = true,
        public readonly string $rewriteSlug = '',
        public readonly string $description = '',
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array{0: ?self, 1: array<string, string>}
     */
    public static function fromInput(array $input): array
    {
        $errors = [];

        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $errors['slug'] = __('The key is required.', 'ctrlfield');
        } elseif (! preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $slug)) {
            $errors['slug'] = __('Use 1–32 lowercase letters, numbers, "-" or "_", starting with a letter.', 'ctrlfield');
        } elseif (ReservedNames::forTaxonomy($slug)) {
            /* translators: %s: taxonomy key */
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

        $postTypes = array_values(array_unique(array_filter(
            array_map(static fn ($t): string => strtolower((string) $t), (array) ($input['post_types'] ?? [])),
            static fn (string $t): bool => (bool) preg_match('/^[a-z0-9_-]{1,20}$/', $t)
        )));

        $rewrite = trim(strtolower(trim((string) ($input['rewrite_slug'] ?? ''))), '/');
        if ($rewrite !== '' && ! preg_match('/^[a-z0-9][a-z0-9\/_-]*$/', $rewrite)) {
            $errors['rewrite_slug'] = __('Use lowercase letters, numbers, "-", "_" or "/".', 'ctrlfield');
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new self(
            slug:            $slug,
            singular:        $singular,
            plural:          $plural,
            postTypes:       $postTypes,
            hierarchical:    self::bool($input['hierarchical'] ?? false),
            public:          self::bool($input['public'] ?? false),
            showInRest:      self::bool($input['show_in_rest'] ?? false),
            showAdminColumn: self::bool($input['show_admin_column'] ?? false),
            rewriteSlug:     $rewrite,
            description:     self::text($input['description'] ?? ''),
        ), []];
    }

    /** @param array<string, mixed> $data stored option row */
    public static function fromArray(array $data): self
    {
        return new self(
            slug:            (string) ($data['slug'] ?? ''),
            singular:        (string) ($data['singular'] ?? ''),
            plural:          (string) ($data['plural'] ?? ''),
            postTypes:       array_values(array_map('strval', (array) ($data['post_types'] ?? []))),
            hierarchical:    (bool) ($data['hierarchical'] ?? true),
            public:          (bool) ($data['public'] ?? true),
            showInRest:      (bool) ($data['show_in_rest'] ?? true),
            showAdminColumn: (bool) ($data['show_admin_column'] ?? true),
            rewriteSlug:     (string) ($data['rewrite_slug'] ?? ''),
            description:     (string) ($data['description'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slug'              => $this->slug,
            'singular'          => $this->singular,
            'plural'            => $this->plural,
            'post_types'        => $this->postTypes,
            'hierarchical'      => $this->hierarchical,
            'public'            => $this->public,
            'show_in_rest'      => $this->showInRest,
            'show_admin_column' => $this->showAdminColumn,
            'rewrite_slug'      => $this->rewriteSlug,
            'description'       => $this->description,
        ];
    }

    private static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    }

    private static function bool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }
}
