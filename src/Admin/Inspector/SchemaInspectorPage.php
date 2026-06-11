<?php

declare(strict_types=1);

namespace FieldForge\Admin\Inspector;

/**
 * Registers the FieldForge top-level admin menu and the Schema Inspector page.
 * Excluded from PHPStan — references WP admin functions.
 */
final class SchemaInspectorPage
{
    public function register(): void
    {
        // Top-level FieldForge menu (the inspector IS the main page).
        add_menu_page(
            page_title: 'FieldForge',
            menu_title: 'FieldForge',
            capability: 'manage_options',
            menu_slug:  'fieldforge',
            callback:   [$this, 'render'],
            icon_url:   'dashicons-database-view',
            position:   65,
        );

        // Rename the auto-created duplicate submenu entry
        add_submenu_page(
            parent_slug:  'fieldforge',
            page_title:   'Schema Inspector',
            menu_title:   'Schema Inspector',
            capability:   'manage_options',
            menu_slug:    'fieldforge',
            callback:     [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.'));
        }

        (new SchemaInspectorRenderer())->render();
    }
}
