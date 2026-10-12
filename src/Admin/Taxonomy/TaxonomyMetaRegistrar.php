<?php

declare(strict_types=1);

namespace CtrlField\Admin\Taxonomy;

use CtrlField\Admin\EntitySave;
use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Builder\AdminContext;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Storage\TermMetaAdapter;

/**
 * Registers CtrlField field groups on WordPress taxonomy term screens.
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

        add_action('admin_notices', [$this, 'printSaveError']);
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
        $renderer->render(0, array_values($groups), []);
    }

    public function renderEdit(\WP_Term $term): void
    {
        $context = new AdminContext(taxonomy: $term->taxonomy);
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        $renderer = new MetaBoxRenderer();
        $renderer->render($term->term_id, array_values($groups), (new TermMetaAdapter())->load($term->term_id) ?? []);
    }

    public function save(int $termId): void
    {
        $taxonomy = sanitize_key($_POST['taxonomy'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress checked the term form nonce; the pipeline checks the CtrlField nonce

        // Verify the user can edit terms in this taxonomy before running the pipeline.
        $taxonomyObj = $taxonomy !== '' ? get_taxonomy($taxonomy) : false;
        $cap         = ($taxonomyObj !== false) ? $taxonomyObj->cap->edit_terms : 'manage_categories';

        if (! current_user_can($cap, $termId)) {
            return;
        }

        EntitySave::run('term', $termId, new TermMetaAdapter(), new AdminContext(taxonomy: $taxonomy), 'edit_term');
    }

    /** Error of the last save, on term.php. */
    public function printSaveError(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->base !== 'term') {
            return;
        }

        EntitySave::printError('term', isset($_GET['tag_ID']) ? (int) $_GET['tag_ID'] : 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which notice to show
    }

    /**
     * Taxonomies named by a `taxonomy` rule of any registered group (AND rules
     * and every whereAny() alternative; `==` and `IN`).
     *
     * @return array<string>
     */
    private function resolveRegisteredTaxonomies(): array
    {
        $taxonomies = [];

        foreach (FieldRegistry::all() as $group) {
            $rules = $group->getAndConditions();
            foreach ($group->getOrGroups() as $alternative) {
                $rules = array_merge($rules, $alternative);
            }

            foreach ($rules as $condition) {
                if ($condition['key'] !== 'taxonomy') {
                    continue;
                }
                $operator = strtolower((string) $condition['operator']);
                if ($operator === '==' || $operator === '=') {
                    $taxonomies[] = (string) $condition['value'];
                } elseif ($operator === 'in' && is_array($condition['value'])) {
                    array_push($taxonomies, ...array_map('strval', $condition['value']));
                }
            }
        }

        return array_values(array_unique($taxonomies));
    }
}
