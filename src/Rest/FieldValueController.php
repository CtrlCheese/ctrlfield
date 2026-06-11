<?php

declare(strict_types=1);

namespace FieldForge\Rest;

use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Data\FieldDataService;
use FieldForge\Registry\ContextRegistry;

/**
 * REST controller for fieldforge/v1/post/{id}.
 *
 * GET  → returns schema + stored values for the post.
 * POST → accepts a JSON payload, runs it through the save pipeline.
 *
 * Excluded from PHPStan — references WP REST API classes.
 */
class FieldValueController
{
    public const NAMESPACE = 'fieldforge/v1';
    public const ROUTE     = '/editor/post/(?P<id>\d+)';  // editor-only; public API is at /post/{id}

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canRead'],
                'args'                => $this->idArg(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canWrite'],
                'args'                => array_merge($this->idArg(), [
                    'payload' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => static fn(mixed $v): string => is_string($v) ? $v : '',
                    ],
                ]),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId   = (int) $request['id'];
        $postType = get_post_type($postId);

        if (! is_string($postType) || $postType === '') {
            return new \WP_REST_Response(['error' => 'Post not found.'], 404);
        }

        $context = new AdminContext(postType: $postType);
        $groups  = ContextRegistry::resolve($context);

        $schema = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $schema[] = $field->getDefinition();
            }
        }

        $values = FieldDataService::getInstance()->getAll($postId, 'post');

        return new \WP_REST_Response([
            'schema' => $schema,
            'values' => $values,
        ], 200);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId  = (int) $request['id'];
        $payload = (string) $request->get_param('payload');

        // WP REST API has already verified nonce (X-WP-Nonce) and capability
        // via permission_callback — skip those pipeline stages here.
        try {
            SavePipeline::runFromRest($postId, $payload);
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 422);
        }

        return new \WP_REST_Response(['success' => true], 200);
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    public function canRead(\WP_REST_Request $request): bool
    {
        return (bool) current_user_can('edit_post', (int) $request['id']);
    }

    public function canWrite(\WP_REST_Request $request): bool
    {
        return (bool) current_user_can('edit_post', (int) $request['id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function idArg(): array
    {
        return [
            'id' => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn(mixed $v): bool => is_numeric($v) && (int) $v > 0,
            ],
        ];
    }
}
