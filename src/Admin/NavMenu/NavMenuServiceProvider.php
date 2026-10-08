<?php

declare(strict_types=1);

namespace CtrlField\Admin\NavMenu;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Data\FieldDataService;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

/**
 * Renders and saves CtrlField fields on WordPress navigation menu items.
 *
 * Fields appear when any FieldGroup is registered with:
 *   ->where('context', '==', 'nav_menu_item')
 *
 * Nav menu items are stored as posts of type nav_menu_item in wp_postmeta,
 * so PostMetaAdapter is used directly (no pipeline — nav menus save via AJAX
 * without a ctrlfield_payload or nonce flow).
 *
 * Excluded from PHPStan — references WP functions.
 */
final class NavMenuServiceProvider extends ServiceProvider
{
    private const NONCE_KEY = 'ctrlfield_nav_menu';

    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('wp_nav_menu_item_custom_fields', [$this, 'renderFields'], 10, 4);
        add_action('wp_update_nav_menu_item',        [$this, 'saveFields'],   10, 3);
    }

    /**
     * @param int        $itemId
     * @param \WP_Post   $item
     * @param int        $depth
     * @param object     $args
     */
    public function renderFields(int $itemId, \WP_Post $item, int $depth, object $args): void
    {
        $context = new AdminContext(contextType: 'nav_menu_item');
        $groups  = ContextRegistry::resolve($context);

        if (empty($groups)) {
            return;
        }

        wp_nonce_field(self::NONCE_KEY . '_' . $itemId, '_ctrlf_nav_nonce_' . $itemId);

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $key   = $field->getKey();
                $def   = $field->getDefinition();
                $label = $def['label'] ?: $key;
                $value = FieldDataService::getInstance()->get($key, $itemId, 'post');

                printf(
                    '<p class="ctrlfield-nav-field description description-wide">'
                    . '<label for="ctrlf_nav_%1$s_%2$d">%3$s<br>'
                    . '<input type="text" id="ctrlf_nav_%1$s_%2$d" name="ctrlf_nav[%2$d][%1$s]" value="%4$s" class="widefat">'
                    . '</label></p>',
                    esc_attr($key),
                    $itemId,
                    esc_html($label),
                    esc_attr((string) $value),
                );
            }
        }
    }

    /**
     * @param int                  $menuId
     * @param int                  $menuItemDbId
     * @param array<string, mixed> $args
     */
    public function saveFields(int $menuId, int $menuItemDbId, array $args): void
    {
        if (! isset($_POST['_ctrlf_nav_nonce_' . $menuItemDbId])) {
            return;
        }

        if (! wp_verify_nonce(
            sanitize_text_field(wp_unslash((string) $_POST['_ctrlf_nav_nonce_' . $menuItemDbId])),
            self::NONCE_KEY . '_' . $menuItemDbId,
        )) {
            return;
        }

        if (! function_exists('current_user_can') || ! current_user_can('edit_theme_options')) {
            return;
        }

        if (! isset($_POST['ctrlf_nav'][$menuItemDbId]) || ! is_array($_POST['ctrlf_nav'][$menuItemDbId])) {
            return;
        }

        $raw    = wp_unslash($_POST['ctrlf_nav'][$menuItemDbId]);
        $fields = [];

        // Only keys of the registered nav menu item fields; other POST keys are ignored.
        foreach (ContextRegistry::resolve(new AdminContext(contextType: 'nav_menu_item')) as $group) {
            foreach ($group->getFields() as $field) {
                $key = $field->getKey();
                if (! array_key_exists($key, $raw)) {
                    continue;
                }
                $value        = $raw[$key];
                $fields[$key] = is_array($value)
                    ? array_map('sanitize_text_field', $value)
                    : sanitize_text_field((string) $value);
            }
        }

        if (empty($fields)) {
            return;
        }

        $existing = FieldDataService::getInstance()->getAll($menuItemDbId, 'post');
        $merged   = array_merge($existing, $fields);

        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $adapter->save($menuItemDbId, $merged, SchemaVersion::CURRENT);
    }
}
