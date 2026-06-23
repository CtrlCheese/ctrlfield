<?php

declare(strict_types=1);

namespace FieldForge\Admin\Taxonomy;

use FieldForge\Admin\MetaBox\MetaBoxRenderer;
use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\SavePipeline;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\TermMetaAdapter;

/**
 * Registers FieldForge field groups on WordPress taxonomy term screens.
 * All Core field types are supported (v1 limitation of text/image/select only is removed).
 * Excluded from PHPStan — references WP functions.
 */
class TaxonomyMetaRegistrar
{
    public function register(): void
    {
        // Collect unique taxonomy slugs that have registered field groups.
        $taxonomies = $this->resolveRegisteredTaxonomies();

        foreach ($taxonomies as $taxonomy) {
            add_action("{$taxonomy}_add_form_fields",  [$this, 'renderAdd']);
            add_action("{$taxonomy}_edit_form_fields", [$this, 'renderEdit']);
            add_action("created_{$taxonomy}",          [$this, 'save']);
            add_action("edited_{$taxonomy}",           [$this, 'save']);
        }
    }

    public function renderAdd(string $taxonomy): void
    {
        $context = new AdminContext(taxonomy: $taxonomy);
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        // Term add form — term ID 0, no saved data yet
        $renderer = new MetaBoxRenderer();
        $renderer->render(0, array_values($groups));
    }

    public function renderEdit(\WP_Term $term): void
    {
        $context = new AdminContext(taxonomy: $term->taxonomy);
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        $renderer = new MetaBoxRenderer();
        $renderer->render($term->term_id, array_values($groups));
    }

    public function save(int $termId): void
    {
        if (! isset($_POST['fieldforge_payload'])) {
            return;
        }

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');

        // Verify the user can edit terms in this taxonomy before running the pipeline.
        $taxonomyObj = $taxonomy !== '' ? get_taxonomy($taxonomy) : false;
        $cap         = ($taxonomyObj !== false) ? $taxonomyObj->cap->edit_terms : 'manage_categories';

        if (! current_user_can($cap, $termId)) {
            return;
        }

        SavePipeline::run(
            entityId:        $termId,
            rawPost:         $_POST,
            adapterOverride: new TermMetaAdapter(),
            contextOverride: new AdminContext(taxonomy: $taxonomy),
        );
    }

    /** @return array<string> */
    private function resolveRegisteredTaxonomies(): array
    {
        $taxonomies = [];

        foreach (ContextRegistry::resolve(new AdminContext()) as $group) {
            foreach ($group->getAndConditions() as $condition) {
                if ($condition['key'] === 'taxonomy' && $condition['operator'] === '==') {
                    $taxonomies[] = (string) $condition['value'];
                }
            }
        }

        return array_unique($taxonomies);
    }
}
