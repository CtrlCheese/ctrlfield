<?php

declare(strict_types=1);

namespace FieldForge\Integrations\REST;

use FieldForge\Builder\AdminContext;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\UserMetaAdapter;

/**
 * GET /wp-json/fieldforge/v1/user/{user_id}
 * Returns fields with showInRest(true) for a user.
 * Excluded from PHPStan — references WP REST API classes.
 */
class UserFieldsController
{
    public function register(): void
    {
        register_rest_route(FieldsController::NAMESPACE, '/user/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFields'],
            'permission_callback' => [$this, 'canRead'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function getFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request['user_id'];

        if (! get_user_by('id', $userId)) {
            return new \WP_REST_Response(['error' => 'User not found.'], 404);
        }

        $context = new AdminContext(contextType: 'user_profile');
        $groups  = ContextRegistry::resolve($context);

        $restKeys = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getDefinition()['rest_exposed'] === true) {
                    $restKeys[] = $field->getKey();
                }
            }
        }

        $stored   = (new UserMetaAdapter())->load($userId) ?? [];
        $filtered = array_intersect_key($stored, array_flip($restKeys));

        return new \WP_REST_Response(['fields' => $filtered], 200);
    }

    /** @return bool|\WP_Error */
    public function canRead(\WP_REST_Request $request): bool|\WP_Error
    {
        $userId = (int) $request['user_id'];

        if (! is_user_logged_in()) {
            return new \WP_Error('rest_not_logged_in', 'Authentication required.', ['status' => 401]);
        }

        return (bool) (get_current_user_id() === $userId || current_user_can('edit_user', $userId));
    }
}
