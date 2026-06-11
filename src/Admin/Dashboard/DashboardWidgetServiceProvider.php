<?php

declare(strict_types=1);

namespace FieldForge\Admin\Dashboard;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Builder\FieldGroup;
use FieldForge\Core\Migration\SchemaVersion;
use FieldForge\Data\FieldDataService;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Storage\Drivers\WpOptionsDriver;
use FieldForge\Storage\OptionsAdapter;

/**
 * Registers FieldGroups configured with ->dashboardWidget() as WP Admin Dashboard widgets.
 *
 * Storage uses OptionsAdapter — dashboard widget data is global to the site.
 * Reading: fieldforge_get_options($key, $groupKey)
 *
 * Excluded from PHPStan — references WP dashboard functions.
 */
final class DashboardWidgetServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        $groups = $this->widgetGroups();
        if (empty($groups)) {
            return;
        }

        add_action('wp_dashboard_setup',                       [$this, 'registerWidgets']);
        add_action('wp_ajax_fieldforge_save_dashboard_widget', [$this, 'saveWidget']);
    }

    public function registerWidgets(): void
    {
        foreach ($this->widgetGroups() as $group) {
            $config = $group->getDashboardWidgetConfig();
            if ($config === null) {
                continue;
            }

            $cap = $group->getRequiredCapability();
            if ($cap !== '' && function_exists('current_user_can') && ! current_user_can($cap)) {
                continue;
            }

            wp_add_dashboard_widget(
                'fieldforge_widget_' . $group->getKey(),
                $config->title,
                function () use ($group): void {
                    $this->renderWidget($group);
                },
                null,
                null,
                $config->context,
                $config->priority,
            );
        }
    }

    public function saveWidget(): void
    {
        check_ajax_referer('fieldforge_dashboard_widget', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
            return;
        }

        $groupKey = isset($_POST['group']) ? sanitize_key((string) $_POST['group']) : '';
        $groups   = FieldRegistry::all();

        if (! isset($groups[$groupKey])) {
            wp_send_json_error(['message' => 'Group not found.'], 400);
            return;
        }

        $group  = $groups[$groupKey];
        $fields = [];

        foreach ($group->getFields() as $field) {
            $key = $field->getKey();
            if (isset($_POST['ff_dw_' . $key])) {
                $fields[$key] = sanitize_text_field((string) $_POST['ff_dw_' . $key]);
            }
        }

        $adapter = new OptionsAdapter(new WpOptionsDriver());
        $adapter->save($groupKey, $fields, SchemaVersion::CURRENT);

        wp_send_json_success(['saved' => true]);
    }

    private function renderWidget(FieldGroup $group): void
    {
        $stored = FieldDataService::getInstance()->getAll($group->getKey(), 'options');
        $nonce  = wp_create_nonce('fieldforge_dashboard_widget');

        echo '<form class="fieldforge-dashboard-widget" data-group="' . esc_attr($group->getKey()) . '" data-nonce="' . esc_attr($nonce) . '">';

        foreach ($group->getFields() as $field) {
            $key   = $field->getKey();
            $label = $field->getDefinition()['label'] ?: $key;
            $value = $stored[$key] ?? '';

            printf(
                '<p><label><strong>%s</strong><br><input type="text" name="ff_dw_%s" value="%s" style="width:100%%"></label></p>',
                esc_html($label),
                esc_attr($key),
                esc_attr((string) $value),
            );
        }

        echo '<input type="submit" class="button button-primary" value="' . esc_attr('Save') . '">';
        echo '</form>';
    }

    /** @return FieldGroup[] */
    private function widgetGroups(): array
    {
        return array_values(
            array_filter(
                FieldRegistry::all(),
                static fn(FieldGroup $g) => $g->isDashboardWidget(),
            ),
        );
    }
}
