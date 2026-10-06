<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\CPT;

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

    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // After schema files (init:5) and theme code (init:10), before registration (init:20).
        add_action('init', [$this, 'registerAdminPostTypes'], 19);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);

        $page = new PostTypesPage(new PostTypeRepository(), new PostTypeCodeExporter());
        add_action('admin_menu', [$page, 'registerMenu'], 20);
        add_action('admin_post_ctrlfield_save_post_type', [$page, 'handleSave']);
        add_action('admin_post_ctrlfield_delete_post_type', [$page, 'handleDelete']);
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
                ->excludeFromSearch(! $def->public)
                ->hierarchical($def->hierarchical)
                ->hasArchive($def->hasArchive)
                ->showInRest($def->showInRest)
                ->menuPosition($def->menuPosition);

            if ($def->rewriteSlug !== '') {
                $cpt->rewriteSlug($def->rewriteSlug);
            }
            if ($def->description !== '') {
                $cpt->description($def->description);
            }

            $cpt->register();
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
}
