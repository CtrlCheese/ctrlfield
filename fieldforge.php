<?php
/**
 * Plugin Name:       FieldForge
 * Plugin URI:        https://fieldforge.dev
 * Description:       Enterprise-grade code-first schema engine for WordPress custom fields, CPTs, and taxonomies.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            ctrlCheese
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fieldforge
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

define('FIELDFORGE_VERSION', '1.0.0');
define('FIELDFORGE_MIN_PHP', '8.2');
define('FIELDFORGE_MIN_WP', '6.4');
define('FIELDFORGE_PATH', plugin_dir_path(__FILE__));
define('FIELDFORGE_URL', plugin_dir_url(__FILE__));
define('FIELDFORGE_FILE', __FILE__);

if (version_compare(PHP_VERSION, FIELDFORGE_MIN_PHP, '<')) {
  add_action('admin_notices', static function (): void {
    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      sprintf(
        /* translators: 1: required PHP version, 2: current PHP version */
        esc_html__('FieldForge requires PHP %1$s or higher. Your server is running PHP %2$s.', 'fieldforge'),
        FIELDFORGE_MIN_PHP,
        PHP_VERSION
      )
    );
  });
  return;
}

if (version_compare($GLOBALS['wp_version'], FIELDFORGE_MIN_WP, '<')) {
  add_action('admin_notices', static function (): void {
    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      sprintf(
        /* translators: 1: required WP version, 2: current WP version */
        esc_html__('FieldForge requires WordPress %1$s or higher. Your installation is running %2$s.', 'fieldforge'),
        FIELDFORGE_MIN_WP,
        $GLOBALS['wp_version']
      )
    );
  });
  return;
}

$autoloader = FIELDFORGE_PATH . 'vendor/autoload.php';

if (!file_exists($autoloader)) {
  add_action('admin_notices', static function (): void {
    echo '<div class="notice notice-error"><p>';
    esc_html_e('FieldForge: Composer dependencies are missing. Run "composer install" in the plugin directory.', 'fieldforge');
    echo '</p></div>';
  });
  return;
}

require_once $autoloader;

add_action('plugins_loaded', static function (): void {
  \FieldForge\Bootstrap\BootManager::boot();
});

if (defined('FIELDFORGE_DEV_TEST') && FIELDFORGE_DEV_TEST) {
  require_once __DIR__ . '/dev-test.php';
}
