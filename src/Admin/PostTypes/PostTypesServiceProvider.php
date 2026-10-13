<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\CPT;
use CtrlField\Builder\Taxonomy;

/**
 * Post types created in CtrlField → Post Types (no code).
 *
 * They are fed into the same CPT builder registry that schema code uses, so
 * WordPressServiceProvider registers both the same way on init:20.
 * Code always wins: a post type defined in code with the same key is kept
 * and the admin-created one is skipped (with a notice on the Post Types page).
 */
final class PostTypesServiceProvider extends ServiceProvider
{
    /** @var string[] admin-created slugs skipped because code defines them */
    private static array $shadowed = [];

    /** @var string[] admin-created taxonomies skipped because code defines them */
    private static array $shadowedTaxonomies = [];

    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // After schema files (init:5) and theme code (init:10), before registration (init:20).
        add_action('init', [$this, 'registerAdminPostTypes'], 19);
        add_action('init', [$this, 'registerAdminTaxonomies'], 19);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);

        $page = new PostTypesPage(new PostTypeRepository(), new PostTypeCodeExporter());
        add_action('admin_menu', [$page, 'registerMenu'], 20);
        add_action('admin_post_ctrlfield_save_post_type', [$page, 'handleSave']);
        add_action('admin_post_ctrlfield_delete_post_type', [$page, 'handleDelete']);
        add_action('admin_post_ctrlfield_save_taxonomy', [$page, 'handleSaveTaxonomy']);
        add_action('admin_post_ctrlfield_delete_taxonomy', [$page, 'handleDeleteTaxonomy']);
        add_action('admin_enqueue_scripts', [$page, 'enqueueAssets']);

        // CtrlField → Visual Builder: post type + taxonomies + field group in one go.
        $wizard = new ContentModelWizard(new PostTypeRepository(), new \CtrlField\Admin\FieldGroups\FieldGroupRepository());
        add_action('admin_menu', [$wizard, 'registerMenu'], 21);
        add_action('admin_enqueue_scripts', [$wizard, 'enqueueAssets']);
        add_action('admin_post_ctrlfield_create_content_model', [$wizard, 'handleCreate']);
    }

    public function registerAdminPostTypes(): void
    {
        foreach ((new PostTypeRepository())->all() as $def) {
            // Code-defined CPTs (builder or plain register_post_type) take precedence.
            if (CPT::has($def->slug) || post_type_exists($def->slug)) {
                self::$shadowed[] = $def->slug;
                continue;
            }

            $cpt = CPT::make($def->slug)
                ->label($def->singular, $def->plural)
                ->menuIcon($def->icon)
                ->supports($def->supports)
                ->public($def->public)
                ->publiclyQueryable($def->public)
                ->excludeFromSearch($def->excludeFromSearch)
                ->hierarchical($def->hierarchical)
                ->hasArchive($def->archive())
                ->showInRest($def->showInRest)
                ->menuPosition($def->menuPosition)
                ->capability($def->capabilityType)
                ->taxonomies($def->taxonomies);

            if ($def->rewriteSlug !== '') {
                $cpt->rewriteSlug($def->rewriteSlug);
            }
            if ($def->restBase !== '') {
                $cpt->restBase($def->restBase);
            }
            if ($def->description !== '') {
                $cpt->description($def->description);
            }

            $cpt->register();
        }
    }

    public function registerAdminTaxonomies(): void
    {
        foreach ((new PostTypeRepository())->taxonomies() as $def) {
            if (Taxonomy::has($def->slug) || taxonomy_exists($def->slug)) {
                self::$shadowedTaxonomies[] = $def->slug;
                continue;
            }

            $taxonomy = Taxonomy::make($def->slug)
                ->label($def->singular, $def->plural)
                ->attachTo($def->postTypes)
                ->hierarchical($def->hierarchical)
                ->public($def->public)
                ->showInRest($def->showInRest)
                ->showAdminColumn($def->showAdminColumn);

            if ($def->rewriteSlug !== '') {
                $taxonomy->rewriteSlug($def->rewriteSlug);
            }
            if ($def->description !== '') {
                $taxonomy->description($def->description);
            }

            $taxonomy->register();
        }
    }

    /** New or changed permalinks only work after the rewrite rules are rebuilt. */
    public function maybeFlushRewriteRules(): void
    {
        if (get_option(PostTypeRepository::FLUSH_FLAG)) {
            delete_option(PostTypeRepository::FLUSH_FLAG);
            flush_rewrite_rules(false);
        }
    }

    /** @return string[] */
    public static function shadowed(): array
    {
        return self::$shadowed;
    }

    /** @return string[] */
    public static function shadowedTaxonomies(): array
    {
        return self::$shadowedTaxonomies;
    }
}
