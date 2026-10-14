<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Schema\JsonGroup;

/**
 * What the content model wizard creates in one go: a post type, new
 * taxonomies for it (plus existing ones linked), and an empty field group
 * shown on it. Everything is validated before anything is saved.
 *
 * Pure: the keys already in use are passed in, so it is unit-testable.
 */
final class ContentModel
{
    /**
     * @param list<TaxonomyDefinition>   $taxonomies new taxonomies
     * @param array<string, mixed>|null  $group      normalized field group, or none
     */
    private function __construct(
        public readonly PostTypeDefinition $postType,
        public readonly array $taxonomies,
        public readonly ?array $group,
    ) {}

    /**
     * @param array<string, mixed> $input  the wizard's form
     * @param array{post_types?: list<string>, taxonomies?: list<string>, groups?: list<string>} $taken keys in use
     * @return array{0: ?self, 1: array<string, string>} the model, or errors keyed "type.slug", "tax.0.plural", "group"…
     */
    public static function fromInput(array $input, array $taken = []): array
    {
        $errors = [];
        $type   = (array) ($input['type'] ?? []);
        $public = in_array($type['public'] ?? '', [true, 1, '1', 'on'], true);

        // Existing taxonomies linked; new ones get the post type themselves.
        $linked = array_values(array_filter(array_map('strval', (array) ($input['link'] ?? []))));

        [$postType, $typeErrors] = PostTypeDefinition::fromInput([
            'slug'          => $type['slug'] ?? '',
            'singular'      => $type['singular'] ?? '',
            'plural'        => $type['plural'] ?? '',
            'icon'          => $type['icon'] ?? '',
            'supports'      => (array) ($type['supports'] ?? ['title', 'editor', 'thumbnail']),
            'public'        => $public ? '1' : '',
            'has_archive'   => ! empty($type['has_archive']) ? '1' : '',
            'show_in_rest'  => '1',
            'menu_position' => 25,
            'taxonomies'    => $linked,
        ]);
        foreach ($typeErrors as $field => $message) {
            $errors['type.' . $field] = $message;
        }
        if ($postType !== null && in_array($postType->slug, $taken['post_types'] ?? [], true)) {
            $errors['type.slug'] = __('This key is already used by another post type.', 'ctrlfield');
        }

        $taxonomies = [];
        $seen       = [];
        foreach (array_values((array) ($input['new'] ?? [])) as $i => $row) {
            $row = (array) $row;
            if (trim((string) ($row['plural'] ?? '')) === '' && trim((string) ($row['singular'] ?? '')) === '') {
                continue; // an empty row left in the form
            }
            [$tax, $taxErrors] = TaxonomyDefinition::fromInput([
                'slug'              => $row['slug'] ?? '',
                'singular'          => $row['singular'] ?? '',
                'plural'            => $row['plural'] ?? '',
                'post_types'        => $postType !== null ? [$postType->slug] : [],
                'hierarchical'      => ! empty($row['hierarchical']) ? '1' : '',
                'public'            => $public ? '1' : '',
                'show_in_rest'      => '1',
                'show_admin_column' => '1',
            ]);
            foreach ($taxErrors as $field => $message) {
                $errors["tax.{$i}.{$field}"] = $message;
            }
            if ($tax === null) {
                continue;
            }
            if (in_array($tax->slug, $taken['taxonomies'] ?? [], true) || isset($seen[$tax->slug])) {
                $errors["tax.{$i}.slug"] = __('This key is already used by another taxonomy.', 'ctrlfield');
                continue;
            }
            $seen[$tax->slug] = true;
            $taxonomies[]     = $tax;
        }

        $group = null;
        if (! empty($input['fields']['create']) && $postType !== null) {
            $key   = self::groupKey($postType->slug);
            $title = trim((string) ($input['fields']['title'] ?? ''));
            [$group, $groupErrors] = JsonGroup::normalize([
                'key'      => $key,
                'title'    => $title !== '' ? $title : $postType->singular,
                'active'   => true,
                'location' => [['key' => 'post_type', 'operator' => '==', 'value' => $postType->slug]],
                'fields'   => [],
            ]);
            if ($groupErrors !== []) {
                $errors['group'] = implode(' ', $groupErrors);
            } elseif (in_array($key, $taken['groups'] ?? [], true)) {
                /* translators: %s: field group key */
                $errors['group'] = sprintf(__('A field group "%s" already exists — untick "Create a field group" or rename the post type key.', 'ctrlfield'), $key);
            }
        }

        if ($errors !== [] || $postType === null) {
            return [null, $errors];
        }

        return [new self($postType, $taxonomies, $group), []];
    }

    /** "team-member" → "team_member_fields" (group keys take no "-"). */
    public static function groupKey(string $postType): string
    {
        return str_replace('-', '_', $postType) . '_fields';
    }
}
