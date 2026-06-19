<?php

declare(strict_types=1);

namespace FieldForge\Integrations\REST;

use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\PipelineException;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Data\FieldDataService;
use FieldForge\Registry\ContextRegistry;

/**
 * Public REST API controller for FieldForge field data.
 *
 * Routes:
 *   GET   /wp-json/fieldforge/v1/post/{post_id}       → field values (opt-in fields)
 *   GET   /wp-json/fieldforge/v1/schema/{post_type}   → field schema definition
 *   PATCH /wp-json/fieldforge/v1/post/{post_id}       → write post fields
 *   PATCH /wp-json/fieldforge/v1/user/{user_id}       → write user fields
 *   PATCH /wp-json/fieldforge/v1/term/{term_id}       → write term fields
 *
 * Excluded from PHPStan — references WP REST API classes.
 */
class FieldsController
{
    public const NAMESPACE = 'fieldforge/v1';

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/post/(?P<post_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getPostFields'],
                'permission_callback' => [$this, 'canReadPost'],
                'args'                => [
                    'post_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn(mixed $v): bool => is_numeric($v) && (int) $v > 0,
                    ],
                ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'updatePostFields'],
                'permission_callback' => [$this, 'canEditPost'],
                'args'                => [
                    'post_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/schema/(?P<post_type>[a-z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSchema'],
            'permission_callback' => [$this, 'canViewSchema'],
            'args'                => [
                'post_type' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/user/(?P<user_id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'updateUserFields'],
            'permission_callback' => [$this, 'canEditUser'],
            'args'                => [
                'user_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/term/(?P<term_id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'updateTermFields'],
            'permission_callback' => [$this, 'canEditTerm'],
            'args'                => [
                'term_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET handlers
    // -------------------------------------------------------------------------

    public function getPostFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request['post_id'];
        $post   = get_post($postId);

        if ($post === null) {
            return new \WP_REST_Response(['error' => 'Post not found.'], 404);
        }

        $context = new AdminContext(postType: $post->post_type);
        $groups  = ContextRegistry::resolve($context);

        $restKeys = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getDefinition()['rest_exposed'] === true) {
                    $restKeys[] = $field->getKey();
                }
            }
        }

        $stored   = FieldDataService::getInstance()->getAll($postId, 'post');
        $filtered = array_intersect_key($stored, array_flip($restKeys));

        return new \WP_REST_Response(['fields' => $filtered], 200);
    }

    public function getSchema(\WP_REST_Request $request): \WP_REST_Response
    {
        $postType = (string) $request['post_type'];
        $context  = new AdminContext(postType: $postType);
        $groups   = ContextRegistry::resolve($context);

        $schema = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $def      = $field->getDefinition();
                $schema[] = [
                    'key'   => $def['key'],
                    'type'  => $def['type'],
                    'label' => $def['label'],
                ];
            }
        }

        return new \WP_REST_Response([
            'post_type' => $postType,
            'fields'    => $schema,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // PATCH handlers
    // -------------------------------------------------------------------------

    public function updatePostFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId  = (int) $request['post_id'];
        $payload = $request->get_json_params();

        if (! isset($payload['fields']) || ! is_array($payload['fields'])) {
            return new \WP_REST_Response(
                ['code' => 'MISSING_FIELDS', 'message' => 'Body must contain a "fields" object.'],
                400
            );
        }

        $jsonPayload = (string) wp_json_encode($payload['fields']);

        try {
            SavePipeline::runFromRest($postId, $jsonPayload);
        } catch (PipelineException $e) {
            return new \WP_REST_Response([
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
                'field'   => $e->fieldKey,
            ], 422);
        }

        $post   = get_post($postId);
        $stored = FieldDataService::getInstance()->getAll($postId, 'post');
        $ctx    = new AdminContext(postType: $post?->post_type ?? '');

        return new \WP_REST_Response(['fields' => $this->filterRestExposed($ctx, $stored)], 200);
    }

    public function updateUserFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId  = (int) $request['user_id'];
        $payload = $request->get_json_params();

        if (! isset($payload['fields']) || ! is_array($payload['fields'])) {
            return new \WP_REST_Response(
                ['code' => 'MISSING_FIELDS', 'message' => 'Body must contain a "fields" object.'],
                400
            );
        }

        $jsonPayload = (string) wp_json_encode($payload['fields']);

        try {
            SavePipeline::runFromRestUser($userId, $jsonPayload);
        } catch (PipelineException $e) {
            return new \WP_REST_Response([
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
                'field'   => $e->fieldKey,
            ], 422);
        }

        $stored = FieldDataService::getInstance()->getAll($userId, 'user');
        $ctx    = new AdminContext(contextType: 'user_profile');

        return new \WP_REST_Response(['fields' => $this->filterRestExposed($ctx, $stored)], 200);
    }

    public function updateTermFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $termId  = (int) $request['term_id'];
        $payload = $request->get_json_params();

        if (! isset($payload['fields']) || ! is_array($payload['fields'])) {
            return new \WP_REST_Response(
                ['code' => 'MISSING_FIELDS', 'message' => 'Body must contain a "fields" object.'],
                400
            );
        }

        $jsonPayload = (string) wp_json_encode($payload['fields']);

        try {
            SavePipeline::runFromRestTerm($termId, $jsonPayload);
        } catch (PipelineException $e) {
            return new \WP_REST_Response([
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
                'field'   => $e->fieldKey,
            ], 422);
        }

        $stored   = FieldDataService::getInstance()->getAll($termId, 'term');
        $termObj  = get_term($termId);
        $taxonomy = ($termObj instanceof \WP_Term) ? $termObj->taxonomy : null;
        $ctx      = new AdminContext(taxonomy: $taxonomy);

        return new \WP_REST_Response(['fields' => $this->filterRestExposed($ctx, $stored)], 200);
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    /** @return bool|\WP_Error */
    public function canViewSchema(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        return (bool) current_user_can('edit_posts');
    }

    /** @return bool|\WP_Error */
    public function canReadPost(\WP_REST_Request $request): bool|\WP_Error
    {
        $postId = (int) $request['post_id'];
        $post   = get_post($postId);

        if ($post === null) {
            return true;
        }

        if ($post->post_status === 'publish') {
            return true;
        }

        if (! is_user_logged_in()) {
            return new \WP_Error(
                'rest_not_logged_in',
                'Authentication is required to read this post.',
                ['status' => 401]
            );
        }

        return (bool) current_user_can('read_post', $postId);
    }

    /** @return bool|\WP_Error */
    public function canEditPost(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        return (bool) current_user_can('edit_post', (int) $request['post_id']);
    }

    /** @return bool|\WP_Error */
    public function canEditUser(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        return (bool) current_user_can('edit_user', (int) $request['user_id']);
    }

    /** @return bool|\WP_Error */
    public function canEditTerm(\WP_REST_Request $request): bool|\WP_Error
    {
        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        $termId = (int) $request['term_id'];
        $term   = get_term($termId);

        if (is_wp_error($term) || $term === null) {
            return new \WP_Error('rest_term_not_found', 'Term not found.', ['status' => 404]);
        }

        $taxonomy = get_taxonomy($term->taxonomy);
        if ($taxonomy === false) {
            return false;
        }

        // Use the taxonomy-specific edit_terms capability instead of the
        // flat manage_categories which grants access to ALL taxonomies.
        return (bool) current_user_can($taxonomy->cap->edit_terms);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Filters stored fields to only those flagged as rest_exposed, matching the
     * behaviour of getPostFields(). Applied to all PATCH responses so callers
     * never receive fields they aren't supposed to see.
     *
     * @param  array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function filterRestExposed(AdminContext $context, array $stored): array
    {
        $groups   = ContextRegistry::resolve($context);
        $restKeys = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getDefinition()['rest_exposed'] === true) {
                    $restKeys[] = $field->getKey();
                }
            }
        }

        return array_intersect_key($stored, array_flip($restKeys));
    }
}
