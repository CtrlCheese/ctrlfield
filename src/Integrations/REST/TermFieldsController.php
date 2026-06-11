<?php

declare(strict_types=1);

namespace FieldForge\Integrations\REST;

use FieldForge\Builder\AdminContext;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\TermMetaAdapter;

/**
 * GET /wp-json/fieldforge/v1/term/{term_id}
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
            'permission_callback' => '__return_true',
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
