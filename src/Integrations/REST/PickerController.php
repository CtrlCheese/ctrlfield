<?php

declare(strict_types=1);

namespace CtrlField\Integrations\REST;

/**
 * Data for the content pickers (Post Object, Relationship, Page Link,
 * Taxonomy, User): one list format, newest first while browsing.
 *
 *   GET /ctrlfield/v1/picker?source=post:page,service&search=&page=1&per_page=7
 *       source = "post:<type>[,<type>…]" | "term:<taxonomy>" | "user:<role>[,<role>…]" ("user:" = every role)
 *   GET /ctrlfield/v1/picker/items?source=…&ids=3,5   the given items (labels of saved values)
 *
 * Results: { id, title, meta, date, thumbnail }. Without a search: posts by
 * last edit, terms and users by creation, newest first; with one, by relevance
 * (posts) or name.
 */
final class PickerController
{
    private const STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    public function register(): void
    {
        $source = ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'];

        register_rest_route(SearchController::NAMESPACE, '/picker', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'canRead'],
            'args'                => [
                'source'   => $source,
                'search'   => ['required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'page'     => ['required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1],
                'per_page' => ['required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50],
                // The post being edited (posts only): not offered as a choice for itself.
                'exclude'  => ['required' => false, 'type' => 'integer', 'default' => 0, 'minimum' => 0],
            ],
        ]);

        register_rest_route(SearchController::NAMESPACE, '/picker/items', [
            'methods'             => 'GET',
            'callback'            => [$this, 'items'],
            'permission_callback' => [$this, 'canRead'],
            'args'                => [
                'source' => $source,
                'ids'    => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    /** Who may list a source: editors of the post types, assigners of the taxonomy, editors for users. */
    public function canRead(\WP_REST_Request $request): bool
    {
        [$kind, $objects] = self::parseSource((string) $request->get_param('source'));

        if ($kind === 'post') {
            if ($objects === []) {
                return false;
            }
            foreach ($objects as $type) {
                $object = get_post_type_object($type);
                if ($object === null || ! current_user_can($object->cap->edit_posts ?? 'edit_posts')) {
                    return false;
                }
            }
            return true;
        }
        if ($kind === 'term') {
            $tax = get_taxonomy($objects[0] ?? '');
            return $tax !== false && current_user_can($tax->cap->assign_terms ?? 'edit_posts');
        }

        // Names and avatars only (no email / login): anyone who edits content.
        return current_user_can('edit_posts');
    }

    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        [$kind, $objects] = self::parseSource((string) $request->get_param('source'));
        $search  = trim((string) $request->get_param('search'));
        $page    = max(1, (int) $request->get_param('page'));
        $perPage = min(50, max(1, (int) $request->get_param('per_page')));

        return new \WP_REST_Response(match ($kind) {
            'term'  => self::terms($objects[0] ?? '', $search, $page, $perPage),
            'user'  => self::users($objects, $search, $page, $perPage),
            default => self::posts($objects, $search, $page, $perPage, (int) $request->get_param('exclude')),
        }, 200);
    }

    public function items(\WP_REST_Request $request): \WP_REST_Response
    {
        [$kind, $objects] = self::parseSource((string) $request->get_param('source'));
        $ids    = array_slice(array_values(array_filter(array_map('intval', explode(',', (string) $request->get_param('ids'))))), 0, 100);
        $out    = [];

        foreach ($ids as $id) {
            $item = match ($kind) {
                // Only terms of the taxonomy the permission check was made for.
                'term'  => ($t = get_term($id)) instanceof \WP_Term && $t->taxonomy === ($objects[0] ?? '') ? self::termResult($t) : null,
                'user'  => ($u = get_userdata($id)) instanceof \WP_User ? self::userResult($u) : null,
                default => ($p = get_post($id)) instanceof \WP_Post && current_user_can('read_post', $id) ? self::postResult($p) : null,
            };
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return new \WP_REST_Response(['results' => $out], 200);
    }

    // -------------------------------------------------------------------------

    /**
     * @param  list<string> $types
     * @return array{results: list<array<string, mixed>>, more: bool}
     */
    private static function posts(array $types, string $search, int $page, int $perPage, int $exclude = 0): array
    {
        $query = new \WP_Query([
            'post__not_in'        => $exclude > 0 ? [$exclude] : [],
            'post_type'           => $types,
            'post_status'         => self::STATUSES,
            's'                   => $search,
            'posts_per_page'      => $perPage,
            'paged'               => $page,
            'orderby'             => $search === '' ? 'modified' : 'relevance',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]);

        $results = [];
        foreach ($query->posts ?? [] as $post) {
            if ($post instanceof \WP_Post && current_user_can('read_post', $post->ID)) {
                $results[] = self::postResult($post);
            }
        }

        return ['results' => $results, 'more' => $page < (int) $query->max_num_pages];
    }

    /** @return array{results: list<array<string, mixed>>, more: bool} */
    private static function terms(string $taxonomy, string $search, int $page, int $perPage): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'search'     => $search,
            'hide_empty' => false,
            'orderby'    => $search === '' ? 'term_id' : 'name',
            'order'      => $search === '' ? 'DESC' : 'ASC',
            'number'     => $perPage + 1,
            'offset'     => ($page - 1) * $perPage,
        ]);
        if (! is_array($terms)) {
            return ['results' => [], 'more' => false];
        }

        return [
            'results' => array_map(self::termResult(...), array_slice($terms, 0, $perPage)),
            'more'    => count($terms) > $perPage,
        ];
    }

    /**
     * @param  list<string> $roles
     * @return array{results: list<array<string, mixed>>, more: bool}
     */
    private static function users(array $roles, string $search, int $page, int $perPage): array
    {
        $args = [
            'number'  => $perPage + 1,
            'offset'  => ($page - 1) * $perPage,
            'orderby' => $search === '' ? 'registered' : 'display_name',
            'order'   => $search === '' ? 'DESC' : 'ASC',
        ];
        if ($search !== '') {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = ['display_name', 'user_nicename']; // never email / login
        }
        if ($roles !== []) {
            $args['role__in'] = $roles;
        }
        $users = get_users($args);

        return [
            'results' => array_values(array_map(self::userResult(...), array_slice($users, 0, $perPage))),
            'more'    => count($users) > $perPage,
        ];
    }

    /** @return array{id: int, title: string, meta: string, date: string, thumbnail: string|null} */
    private static function postResult(\WP_Post $post): array
    {
        $type   = get_post_type_object($post->post_type);
        $status = $post->post_status === 'publish' ? '' : (string) (get_post_status_object($post->post_status)->label ?? $post->post_status);
        $thumb  = get_the_post_thumbnail_url($post, 'thumbnail');

        return [
            'id'        => $post->ID,
            'title'     => $post->post_title !== '' ? $post->post_title : __('(no title)', 'ctrlfield'),
            'meta'      => implode(' · ', array_filter([$type !== null ? (string) $type->labels->singular_name : $post->post_type, $status])),
            'date'      => (string) get_the_modified_date('', $post),
            'thumbnail' => $thumb ?: null,
        ];
    }

    /** @return array{id: int, title: string, meta: string, date: string, thumbnail: string|null} */
    private static function termResult(\WP_Term $term): array
    {
        $tax = get_taxonomy($term->taxonomy);

        return [
            'id'        => $term->term_id,
            'title'     => $term->name,
            /* translators: %d: number of posts using the term */
            'meta'      => ($tax !== false ? (string) $tax->labels->singular_name . ' · ' : '') . sprintf(_n('%d post', '%d posts', $term->count, 'ctrlfield'), $term->count),
            'date'      => '',
            'thumbnail' => null,
        ];
    }

    /** @return array{id: int, title: string, meta: string, date: string, thumbnail: string|null} */
    private static function userResult(\WP_User $user): array
    {
        $roles = wp_roles()->get_names();
        $role  = $user->roles[0] ?? '';

        return [
            'id'        => $user->ID,
            'title'     => $user->display_name,
            'meta'      => $role !== '' ? translate_user_role((string) ($roles[$role] ?? $role)) : '',
            'date'      => (string) mysql2date(get_option('date_format'), $user->user_registered),
            'thumbnail' => get_avatar_url($user->ID, ['size' => 64]) ?: null,
        ];
    }

    /** @return array{0: 'post'|'term'|'user', 1: list<string>} */
    private static function parseSource(string $source): array
    {
        [$kind, $list] = array_pad(explode(':', $source, 2), 2, '');
        $objects = array_values(array_filter(array_map('sanitize_key', explode(',', $list))));

        return [in_array($kind, ['term', 'user'], true) ? $kind : 'post', $objects];
    }
}
