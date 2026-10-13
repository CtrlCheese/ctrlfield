<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

use CtrlField\Builder\CPT;

/**
 * Puts the post types CtrlField registers (admin-made and CPT::make()) into
 * one "CPTs" menu once there are at least two, sorted alphabetically, with
 * an overview page — so a site with many post types keeps a short menu.
 * Post types of other plugins and themes keep their own menus.
 *
 * WordPress drops a taxonomy's menu when its post type is a sub-menu, so
 * the taxonomies are listed under their post type here (indented).
 *
 * Off with: add_filter('ctrlfield/cpt_menu_folder', '__return_false');
 * or the switch on CtrlField → Post Types.
 *
 * Excluded from PHPStan — WP admin globals ($submenu).
 */
final class CptMenuFolder
{
    public const SLUG   = 'ctrlfield-cpts';
    public const OPTION = 'ctrlfield_cpt_menu_folder';
    public const MIN    = 2;

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 9); // before core adds the post type sub-menus (10)
        add_action('admin_menu', [$this, 'orderSubmenu'], 999);
        add_filter('parent_file', [$this, 'parentFile']);
        add_filter('submenu_file', [$this, 'submenuFile'], 10, 2);
        add_action('admin_head', [$this, 'menuStyles']);
    }

    public static function enabled(): bool
    {
        $on = function_exists('get_option') ? (bool) get_option(self::OPTION, true) : true;

        return function_exists('apply_filters') ? (bool) apply_filters('ctrlfield/cpt_menu_folder', $on) : $on;
    }

    /**
     * The post types that go into the folder, alphabetically by name: those
     * with their own top-level menu, when there are at least MIN of them.
     *
     * @param  array<string, CPT> $cpts
     * @return list<string>
     */
    public static function grouped(array $cpts, ?bool $enabled = null): array
    {
        if (! ($enabled ?? self::enabled())) {
            return [];
        }
        $names = [];
        foreach ($cpts as $key => $cpt) {
            if ($cpt->isShowUi() && $cpt->getShowInMenu() === true) {
                $names[(string) $key] = $cpt->getPluralLabel() !== '' ? $cpt->getPluralLabel() : (string) $key;
            }
        }
        if (count($names) < self::MIN) {
            return [];
        }
        uasort($names, static fn (string $a, string $b): int => self::compare($a, $b));

        return array_map('strval', array_keys($names));
    }

    /** Alphabetical in the site's language ("Écoles" next to "Ecoles"). */
    private static function compare(string $a, string $b): int
    {
        static $collator = null;
        if ($collator === null && class_exists(\Collator::class)) {
            $collator = new \Collator(function_exists('get_locale') ? get_locale() : 'en_US');
        }

        return $collator instanceof \Collator ? (int) $collator->compare($a, $b) : strnatcasecmp($a, $b);
    }

    /** @return list<string> */
    private static function current(): array
    {
        return self::grouped(CPT::all());
    }

    // ── Menu ─────────────────────────────────────────────────────────────────

    public function registerMenu(): void
    {
        if (self::current() === []) {
            return;
        }
        $label = (string) apply_filters('ctrlfield/cpt_menu_folder_label', __('CPTs', 'ctrlfield'));
        add_menu_page($label, $label, 'edit_posts', self::SLUG, [$this, 'render'], 'dashicons-open-folder', 25.9);
        add_submenu_page(self::SLUG, $label, __('Overview', 'ctrlfield'), 'edit_posts', self::SLUG, [$this, 'render']);
    }

    /**
     * Overview first, then each post type alphabetically with its taxonomies
     * right under it.
     */
    public function orderSubmenu(): void
    {
        global $submenu;
        $keys = self::current();
        if ($keys === [] || ! isset($submenu[self::SLUG])) {
            return;
        }

        $items = [];
        foreach ($submenu[self::SLUG] as $item) {
            $items[(string) $item[2]] = $item;
        }
        $ordered = isset($items[self::SLUG]) ? [$items[self::SLUG]] : [];
        foreach ($keys as $type) {
            $href = 'edit.php?post_type=' . $type;
            if (isset($items[$href])) {
                $ordered[] = $items[$href];
                unset($items[$href]);
            }
            foreach (get_object_taxonomies($type, 'objects') as $taxonomy) {
                if (! $taxonomy->show_ui || ! $taxonomy->show_in_menu || ! current_user_can($taxonomy->cap->manage_terms)) {
                    continue;
                }
                $ordered[] = [
                    (string) $taxonomy->labels->menu_name,
                    $taxonomy->cap->manage_terms,
                    'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $type,
                    '',
                    'ctrlf-cpts-tax',
                ];
            }
        }
        unset($items[self::SLUG]);
        $submenu[self::SLUG] = [...$ordered, ...array_values($items)]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering our own menu
    }

    /** Term screens of a grouped post type keep the folder open. */
    public function parentFile(?string $file): ?string
    {
        global $typenow, $pagenow;
        if (in_array($pagenow, ['edit-tags.php', 'term.php'], true) && is_string($typenow) && in_array($typenow, self::current(), true)) {
            return self::SLUG;
        }

        return $file;
    }

    public function submenuFile(?string $file, ?string $parent): ?string
    {
        global $typenow, $taxnow, $pagenow;
        if ($parent === self::SLUG && in_array($pagenow, ['edit-tags.php', 'term.php'], true) && is_string($taxnow) && $taxnow !== '') {
            return 'edit-tags.php?taxonomy=' . $taxnow . '&post_type=' . $typenow;
        }

        return $file;
    }

    /** Taxonomies indented under their post type. */
    public function menuStyles(): void
    {
        if (self::current() === []) {
            return;
        }
        echo '<style>#toplevel_page_' . esc_attr(self::SLUG) . ' .wp-submenu a[href*="edit-tags.php"]{padding-left:24px;font-size:12px;opacity:.85}'
            . '#toplevel_page_' . esc_attr(self::SLUG) . ' .wp-submenu a[href*="edit-tags.php"]::before{content:"– ";}</style>';
    }

    // ── Overview page ────────────────────────────────────────────────────────

    public function render(): void
    {
        $cpts = CPT::all();
        ?>
        <div class="wrap ctrlf-types-page">
            <h1><?php echo esc_html((string) apply_filters('ctrlfield/cpt_menu_folder_label', __('CPTs', 'ctrlfield'))); ?></h1>
            <p class="ctrlf-types__lead"><?php esc_html_e('Every content type of this site in one place.', 'ctrlfield'); ?>
                <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => PostTypesPage::SLUG], admin_url('admin.php'))); ?>"><?php esc_html_e('Manage post types', 'ctrlfield'); ?></a>
                <?php endif; ?>
            </p>
            <ul class="ctrlf-cpts">
                <?php foreach (self::current() as $type):
                    $object = get_post_type_object($type);
                    if ($object === null || ! current_user_can($object->cap->edit_posts)) {
                        continue;
                    }
                    $counts = wp_count_posts($type);
                    $icon   = $cpts[$type]->getMenuIcon();
                    ?>
                    <li class="ctrlf-cpts__item">
                        <a class="ctrlf-cpts__main" href="<?php echo esc_url(admin_url('edit.php?post_type=' . $type)); ?>">
                            <span class="ctrlf-types__icon">
                                <?php if (str_starts_with($icon, 'dashicons-')): ?>
                                    <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                                <?php else: ?>
                                    <img src="<?php echo esc_url($icon); ?>" alt="" width="20" height="20">
                                <?php endif; ?>
                            </span>
                            <span>
                                <strong><?php echo esc_html((string) $object->labels->name); ?></strong>
                                <span class="ctrlf-types__meta">
                                    <?php
                                    echo esc_html(sprintf(
                                        /* translators: 1: published entries, 2: drafts */
                                        __('%1$s published · %2$s drafts', 'ctrlfield'),
                                        number_format_i18n((int) ($counts->publish ?? 0)),
                                        number_format_i18n((int) ($counts->draft ?? 0))
                                    ));
                                    ?>
                                </span>
                            </span>
                        </a>
                        <div class="ctrlf-cpts__links">
                            <?php if (current_user_can($object->cap->create_posts)): ?>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $type)); ?>"><?php echo esc_html((string) $object->labels->add_new_item); ?></a>
                            <?php endif; ?>
                            <?php foreach (get_object_taxonomies($type, 'objects') as $taxonomy): ?>
                                <?php if ($taxonomy->show_ui && current_user_can($taxonomy->cap->manage_terms)): ?>
                                    <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $type)); ?>"><?php echo esc_html((string) $taxonomy->labels->name); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
