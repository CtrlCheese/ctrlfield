<?php

declare(strict_types=1);

namespace FieldForge\Integrations\REST;

/**
 * Read-only AJAX-search endpoints consumed by PostObjectField and TaxonomyField (Pro).
 * Registered in Core because they are useful beyond Pro fields (e.g., custom admin UIs).
 *
 * Excluded from PHPStan — references WP REST API classes.
 *
 * Routes:
 *   GET /wp-json/fieldforge/v1/search/posts?post_type=portfolio&search=acme&per_page=20&page=1
 *   GET /wp-json/fieldforge/v1/search/terms?taxonomy=category&search=design&per_page=20&hide_empty=1
 *   GET /wp-json/fieldforge/v1/search/users?search=john&roles=editor,author&per_page=20
 */
final class SearchController
{
    public const NAMESPACE = 'fieldforge/v1';

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/search/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchPosts'],
            'permission_callback' => [$this, 'canSearch'],
            'args'                => [
                'post_type' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
                'search'    => ['required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'per_page'  => ['required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page'      => ['required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/search/terms', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchTerms'],
            'permission_callback' => [$this, 'canSearch'],
            'args'                => [
                'taxonomy'   => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
                'search'     => ['required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'per_page'   => ['required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'hide_empty' => ['required' => false, 'type' => 'boolean', 'default' => false],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/search/users', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchUsers'],
            'permission_callback' => [$this, 'canSearchUsers'],
            'args'                => [
                'search'   => ['required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'roles'    => ['required' => false, 'type' => 'string', 'default' => ''],
                'per_page' => ['required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
            ],
        ]);
    }

    /** @return bool|\WP_Error */
    public function canSearch(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        // posts search: validate the requested post type exists and user can edit it
        $postType = (string) ($request->get_param('post_type') ?? '');
        if ($postType !== '') {
            $ptObject = get_post_type_object($postType);
            if ($ptObject === null) {
                return new \WP_Error('invalid_post_type', 'Invalid post type.', ['status' => 400]);
            }
            $editCap = $ptObject->cap->edit_posts ?? 'edit_posts';
            if (! current_user_can($editCap)) {
                return false;
            }
            return true;
        }

        // terms search: validate taxonomy exists and user can manage it
        $taxonomy = (string) ($request->get_param('taxonomy') ?? '');
        if ($taxonomy !== '') {
            $taxObject = get_taxonomy($taxonomy);
            if ($taxObject === false) {
                return new \WP_Error('invalid_taxonomy', 'Invalid taxonomy.', ['status' => 400]);
            }
            $manageCap = $taxObject->cap->manage_terms ?? 'manage_categories';
            if (! current_user_can($manageCap)) {
                return false;
            }
            return true;
        }

        return current_user_can('edit_posts');
    }

    public function searchPosts(\WP_REST_Request $request): \WP_REST_Response
    {
        $postType = (string) $request->get_param('post_type');
        $search   = (string) $request->get_param('search');
        $perPage  = min(100, max(1, (int) $request->get_param('per_page')));
        $page     = max(1, (int) $request->get_param('page'));

        $query = new \WP_Query([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            's'              => $search,
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'fields'         => 'all',
            'no_found_rows'  => false,
        ]);

        $results = [];

        foreach ($query->posts as $post) {
            $thumbnail = get_the_post_thumbnail_url($post->ID, [60, 60]);
            $results[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'thumbnail' => $thumbnail ?: null,
            ];
        }

        return new \WP_REST_Response([
            'results' => $results,
            'total'   => (int) $query->found_posts,
            'pages'   => (int) $query->max_num_pages,
        ], 200);
    }

    public function searchTerms(\WP_REST_Request $request): \WP_REST_Response
    {
        $taxonomy  = (string) $request->get_param('taxonomy');
        $search    = (string) $request->get_param('search');
        $perPage   = min(100, max(1, (int) $request->get_param('per_page')));
        $hideEmpty = (bool) $request->get_param('hide_empty');

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'search'     => $search,
            'number'     => $perPage,
            'hide_empty' => $hideEmpty,
            'fields'     => 'all',
        ]);

        if (is_wp_error($terms)) {
            return new \WP_REST_Response(['results' => [], 'total' => 0], 200);
        }

        $results = [];

        foreach ($terms as $term) {
            $results[] = [
                'id'    => $term->term_id,
                'name'  => $term->name,
                'count' => $term->count,
            ];
        }

        $total = wp_count_terms(['taxonomy' => $taxonomy, 'search' => $search, 'hide_empty' => $hideEmpty]);

        return new \WP_REST_Response([
            'results' => $results,
            'total'   => is_wp_error($total) ? count($results) : (int) $total,
        ], 200);
    }

    /** @return bool|\WP_Error */
    public function canSearchUsers(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        if (! current_user_can('list_users')) {
            return new \WP_Error('rest_forbidden', 'Insufficient permissions.', ['status' => 403]);
        }

        return true;
    }

    public function searchUsers(\WP_REST_Request $request): \WP_REST_Response
    {
        $search  = (string) $request->get_param('search');
        $rolesRaw = (string) $request->get_param('roles');
        $perPage = min(100, max(1, (int) $request->get_param('per_page')));

        $args = [
            'number'  => $perPage,
            'search'  => '*' . $search . '*',
            'fields'  => 'all',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ];

        if ($rolesRaw !== '') {
            $roles        = array_filter(array_map('sanitize_key', explode(',', $rolesRaw)));
            $args['role__in'] = array_values($roles);
        }

        $users   = get_users($args);
        $results = [];

        foreach ($users as $user) {
            $results[] = [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar_url'   => get_avatar_url($user->ID, ['size' => 24]),
            ];
        }

        return new \WP_REST_Response(['results' => $results, 'total' => count($results)], 200);
    }
}
