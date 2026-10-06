<?php

declare(strict_types=1);

namespace CtrlField\Admin\Map;

/**
 * Settings page for map provider API keys.
 * Excluded from PHPStan — uses WP admin functions.
 */
final class MapSettingsPage
{
    public const GOOGLE_KEY_OPTION  = '_ctrlfield_pro_google_maps_api_key';
    public const MAPBOX_KEY_OPTION  = '_ctrlfield_pro_mapbox_api_key';

    public function register(): void
    {
        add_submenu_page(
            'ctrlfield',
            __('Map Settings', 'ctrlfield'),
            __('Map Settings', 'ctrlfield'),
            'manage_options',
            'ctrlfield-map-settings',
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'ctrlfield'));
        }

        if (isset($_POST['ctrlfield_map_settings_nonce'])
            && wp_verify_nonce(sanitize_key($_POST['ctrlfield_map_settings_nonce']), 'ctrlfield_map_settings')
        ) {
            $this->handleSave();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'ctrlfield') . '</p></div>';
        }

        $googleKey = (string) get_option(self::GOOGLE_KEY_OPTION, '');
        $mapboxKey = (string) get_option(self::MAPBOX_KEY_OPTION, '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CtrlField Pro — Map Settings', 'ctrlfield'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('ctrlfield_map_settings', 'ctrlfield_map_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ctrlf_google_api_key"><?php esc_html_e('Google Maps API Key', 'ctrlfield'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="ctrlf_google_api_key" name="ctrlf_google_api_key"
                                   class="regular-text" value="<?php echo esc_attr($googleKey); ?>">
                            <p class="description">
                                <?php esc_html_e('Required for Google Maps provider and geocoding.', 'ctrlfield'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ctrlf_mapbox_api_key"><?php esc_html_e('Mapbox API Key', 'ctrlfield'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="ctrlf_mapbox_api_key" name="ctrlf_mapbox_api_key"
                                   class="regular-text" value="<?php echo esc_attr($mapboxKey); ?>">
                            <p class="description">
                                <?php esc_html_e('Required for Mapbox provider. Leave blank to use OpenStreetMap.', 'ctrlfield'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'ctrlfield'); ?>
                    </button>
                </p>
            </form>

            <hr>
            <h2><?php esc_html_e('OpenStreetMap', 'ctrlfield'); ?></h2>
            <p><?php esc_html_e('No API key required. Uses the free Nominatim geocoding service (1 request/second limit).', 'ctrlfield'); ?></p>
        </div>
        <?php
    }

    private function handleSave(): void
    {
        $googleKey = sanitize_text_field(wp_unslash($_POST['ctrlf_google_api_key'] ?? ''));
        $mapboxKey = sanitize_text_field(wp_unslash($_POST['ctrlf_mapbox_api_key'] ?? ''));

        update_option(self::GOOGLE_KEY_OPTION, $googleKey, false);
        update_option(self::MAPBOX_KEY_OPTION, $mapboxKey, false);
    }
}
