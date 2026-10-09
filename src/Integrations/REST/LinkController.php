<?php

declare(strict_types=1);

namespace CtrlField\Integrations\REST;

use CtrlField\Data\FieldDataService;

/**
 * Data for the Link field dialog.
 *
 *   GET /ctrlfield/v1/link/search?source=post:page&search=about&page=1
 *       source = "post:<type>[,<type>…]" or "term:<taxonomy>"
 *   GET /ctrlfield/v1/link/anchors?post_id=12
 *   GET /ctrlfield/v1/link/status?links=post:12,term:5
 */
final class LinkController
{
    private const PER_PAGE = 20;

    /** Statuses a link may point to; anything else is not offered. */
    private const STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    public function register(): void
    {
        register_rest_route(SearchController::NAMESPACE, '/link/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => [$this, 'canEdit'],
            'args'                => [
                'source' => ['required' => true, 'type' => 'string'],
                'search' => ['required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'page'   => ['required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1],
            ],
        ]);

        register_rest_route(SearchController::NAMESPACE, '/link/anchors', [
            'methods'             => 'GET',
            'callback'            => [$this, 'anchors'],
            'permission_callback' => [$this, 'canEditPost'],
            'args'                => [
                'post_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
            ],
        ]);

        register_rest_route(SearchController::NAMESPACE, '/link/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'status'],
            'permission_callback' => [$this, 'canEdit'],
            'args'                => [
                'links' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function canEdit(): bool
    {
        return current_user_can('edit_posts');
    }

    public function canEditPost(\WP_REST_Request $request): bool
    {
        return current_user_can('edit_post', (int) $request->get_param('post_id'));
    }

    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        [$kind, $objects] = self::parseSource((string) $request->get_param('source'));
        $search = (string) $request->get_param('search');
        $page   = max(1, (int) $request->get_param('page'));

        if ($kind === 'term') {
            return new \WP_REST_Response(self::searchTerms($objects[0] ?? '', $search, $page), 200);
        }

        // Only post types that exist and are public (what a visitor can open).
        $types = array_values(array_filter($objects, static function (string $type): bool {
            $object = get_post_type_object($type);
            return $object !== null && $object->public && $type !== 'attachment';
        }));
        if ($types === []) {
            return new \WP_REST_Response(['results' => [], 'more' => false], 200);
        }

        $query = new \WP_Query([
            'post_type'           => $types,
            'post_status'         => self::STATUSES,
            's'                   => $search,
            'posts_per_page'      => self::PER_PAGE,
            'paged'               => $page,
            // Latest edits first while browsing; relevance when searching.
            'orderby'             => $search === '' ? 'modified' : 'relevance',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]);

        $results = [];
        foreach ($query->posts ?? [] as $post) {
            if (! $post instanceof \WP_Post || ! current_user_can('read_post', $post->ID)) {
                continue;
            }
            $results[] = self::postResult($post);
        }

        return new \WP_REST_Response([
            'results' => $results,
            'more'    => $page < (int) $query->max_num_pages,
        ], 200);
    }

    /** Anchors (#id) found in the saved content and fields of a post. */
    public function anchors(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId  = (int) $request->get_param('post_id');
        $found   = [];
        $content = (string) get_post_field('post_content', $postId);

        self::collectAnchors($content, $found);
        self::collectAnchors(FieldDataService::getInstance()->getAll($postId, 'post'), $found);

        $anchors = [];
        foreach (array_keys($found) as $anchor) {
            $anchors[] = ['anchor' => (string) $anchor, 'label' => '#' . $anchor];
        }

        /**
         * Anchors offered in the Link dialog for a post.
         *
         * @param list<array{anchor: string, label: string}> $anchors
         * @param int                                        $postId
         */
        $anchors = apply_filters('ctrlfield/link/anchors', $anchors, $postId);

        return new \WP_REST_Response(['results' => self::cleanAnchors($anchors)], 200);
    }

    /** Current URL / title of links saved earlier, and whether their target still exists. */
    public function status(\WP_REST_Request $request): \WP_REST_Response
    {
        $out = [];
        foreach (array_slice(explode(',', (string) $request->get_param('links')), 0, 100) as $ref) {
            if (! preg_match('/^(post|term):(\d+)$/', trim($ref), $m)) {
                continue;
            }
            $id = (int) $m[2];
            if ($m[1] === 'post') {
                $post = get_post($id);
                $ok   = $post instanceof \WP_Post && in_array($post->post_status, self::STATUSES, true)
                    && current_user_can('read_post', $id);
                $out[$ref] = $ok ? ['exists' => true] + self::postResult($post) : ['exists' => false];
            } else {
                $term      = get_term($id);
                $out[$ref] = $term instanceof \WP_Term
                    ? ['exists' => true, 'title' => $term->name, 'url' => self::termLink($term)]
                    : ['exists' => false];
            }
        }

        return new \WP_REST_Response(['results' => $out], 200);
    }

    // -------------------------------------------------------------------------

    /** @return array{0: string, 1: list<string>} */
    private static function parseSource(string $source): array
    {
        [$kind, $list] = array_pad(explode(':', $source, 2), 2, '');
        $objects = array_values(array_filter(array_map('sanitize_key', explode(',', $list))));

        return [$kind === 'term' ? 'term' : 'post', $objects];
    }

    /** @return array{results: list<array<string, mixed>>, more: bool} */
    private static function searchTerms(string $taxonomy, string $search, int $page): array
    {
        $tax = get_taxonomy($taxonomy);
        if ($tax === false || ! $tax->public) {
            return ['results' => [], 'more' => false];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'search'     => $search,
            'hide_empty' => false,
            'number'     => self::PER_PAGE + 1,
            'offset'     => ($page - 1) * self::PER_PAGE,
        ]);
        if (! is_array($terms)) {
            return ['results' => [], 'more' => false];
        }

        $results = [];
        foreach (array_slice($terms, 0, self::PER_PAGE) as $term) {
            $results[] = [
                'kind'      => 'term',
                'id'        => $term->term_id,
                'object'    => $taxonomy,
                'title'     => $term->name,
                'typeLabel' => (string) $tax->labels->singular_name,
                'status'    => '',
                'date'      => '',
                'url'       => self::termLink($term),
                'thumbnail' => null,
            ];
        }

        return ['results' => $results, 'more' => count($terms) > self::PER_PAGE];
    }

    private static function termLink(\WP_Term $term): string
    {
        $url = get_term_link($term);

        return is_string($url) ? $url : '';
    }

    /**
     * Whatever a filter returned, as a clean anchor list.
     *
     * @return list<array{anchor: string, label: string}>
     */
    private static function cleanAnchors(mixed $anchors): array
    {
        $out = [];
        foreach (is_array($anchors) ? $anchors : [] as $a) {
            if (is_array($a) && isset($a['anchor']) && is_string($a['anchor']) && $a['anchor'] !== '') {
                $out[] = ['anchor' => ltrim($a['anchor'], '#'), 'label' => (string) ($a['label'] ?? '#' . ltrim($a['anchor'], '#'))];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function postResult(\WP_Post $post): array
    {
        $type   = get_post_type_object($post->post_type);
        $status = get_post_status_object($post->post_status);
        $thumb  = get_the_post_thumbnail_url($post, 'thumbnail');

        return [
            'kind'      => 'post',
            'id'        => $post->ID,
            'object'    => $post->post_type,
            'title'     => $post->post_title !== '' ? $post->post_title : __('(no title)', 'ctrlfield'),
            'typeLabel' => $type !== null ? (string) $type->labels->singular_name : $post->post_type,
            'status'    => $post->post_status === 'publish' ? '' : ($status !== null ? (string) $status->label : $post->post_status),
            'date'      => (string) get_the_modified_date('', $post),
            'url'       => (string) get_permalink($post),
            'thumbnail' => $thumb ?: null,
        ];
    }

    /**
     * id="…" attributes in HTML strings, and values of fields whose name says
     * "anchor" (anchor, anchor_id, sectionAnchor…), at any depth.
     *
     * @param array<string, true> $found
     */
    private static function collectAnchors(mixed $value, array &$found, string $key = ''): void
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                self::collectAnchors($v, $found, (string) $k);
            }
            return;
        }
        if (! is_string($value) || $value === '') {
            return;
        }
        if (stripos($key, 'anchor') !== false && preg_match('/^#?([A-Za-z][A-Za-z0-9_:.-]{0,63})$/', trim($value), $m)) {
            $found[$m[1]] = true;
        }
        if (str_contains($value, 'id=') && preg_match_all('/\bid=["\']([A-Za-z][A-Za-z0-9_:.-]{0,63})["\']/', $value, $all)) {
            foreach ($all[1] as $anchor) {
                $found[$anchor] = true;
            }
        }
    }
}
