<?php

declare(strict_types=1);

namespace CtrlField\Integrations\REST;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\TermMetaAdapter;

/**
 * GET /wp-json/ctrlfield/v1/term/{term_id}
 * Returns fields with showInRest(true) for a taxonomy term.
 * Excluded from PHPStan — references WP REST API classes.
 */
class TermFieldsController
{
    public function register(): void
    {
        register_rest_route(FieldsController::NAMESPACE, '/term/(?P<term_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFields'],
            'permission_callback' => [$this, 'canReadTerm'],
            'args'                => [
                'term_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /** @return bool|\WP_Error */
    public function canReadTerm(\WP_REST_Request $request): bool|\WP_Error
    {
        $termId   = (int) $request['term_id'];
        $term     = get_term($termId);

        if (! $term instanceof \WP_Term) {
            return true; // getFields() returns 404
        }

        $taxonomy = get_taxonomy($term->taxonomy);

        // Public taxonomies (categories, tags, etc.) mirror WP core REST behaviour:
        // term data is publicly readable.
        if ($taxonomy !== false && $taxonomy->public) {
            return true;
        }

        // Private / internal taxonomies require the user to be logged in.
        if (! is_user_logged_in()) {
            return new \WP_Error(
                'rest_not_logged_in',
                'Authentication required to read this term.',
                ['status' => 401]
            );
        }

        $cap = $taxonomy !== false ? ($taxonomy->cap->edit_terms ?? 'manage_categories') : 'manage_categories';

        return (bool) current_user_can($cap);
    }

    public function getFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $termId = (int) $request['term_id'];
        $term   = get_term($termId);

        if (! $term instanceof \WP_Term) {
            return new \WP_REST_Response(['error' => 'Term not found.'], 404);
        }

        $context = new AdminContext(taxonomy: $term->taxonomy);
        $groups  = ContextRegistry::resolve($context);

        $restKeys = [];
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if ($field->getDefinition()['rest_exposed'] === true) {
                    $restKeys[] = $field->getKey();
                }
            }
        }

        $stored   = (new TermMetaAdapter())->load($termId) ?? [];
        $filtered = array_intersect_key($stored, array_flip($restKeys));

        return new \WP_REST_Response(['fields' => $filtered], 200);
    }
}
