<?php
/**
 * Plugin Name:       CtrlField
 * Plugin URI:        https://ctrlcheese.de
 * Description:       Code-first custom fields, CPTs and taxonomies for WordPress. A Pro license unlocks flexible content, relational fields, the visual builder and more.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            CtrlCheese
 * Author URI:        https://ctrlcheese.de
 * Update URI:        https://ctrlcheese.de/ctrlfield
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ctrlfield
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

define('CTRLFIELD_VERSION', '1.0.1');
define('CTRLFIELD_MIN_PHP', '8.2');
define('CTRLFIELD_MIN_WP', '6.5');
define('CTRLFIELD_PATH', plugin_dir_path(__FILE__));
define('CTRLFIELD_URL', plugin_dir_url(__FILE__));
define('CTRLFIELD_FILE', __FILE__);

if (version_compare(PHP_VERSION, CTRLFIELD_MIN_PHP, '<')) {
  add_action('admin_notices', static function (): void {
    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      sprintf(
        /* translators: 1: required PHP version, 2: current PHP version */
        esc_html__('CtrlField requires PHP %1$s or higher. Your server is running PHP %2$s.', 'ctrlfield'),
        esc_html(CTRLFIELD_MIN_PHP),
        esc_html(PHP_VERSION)
      )
    );
  });
  return;
}

if (version_compare($GLOBALS['wp_version'], CTRLFIELD_MIN_WP, '<')) {
  add_action('admin_notices', static function (): void {
    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      sprintf(
        /* translators: 1: required WP version, 2: current WP version */
        esc_html__('CtrlField requires WordPress %1$s or higher. Your installation is running %2$s.', 'ctrlfield'),
        esc_html(CTRLFIELD_MIN_WP),
        esc_html($GLOBALS['wp_version'])
      )
    );
  });
  return;
}

// Installed with `composer require` in a project (Bedrock, …): the project's
// autoloader already knows CtrlField. Installed from the zip: vendor/ ships with it.
$autoloader = CTRLFIELD_PATH . 'vendor/autoload.php';

if (!class_exists(\CtrlField\Bootstrap\BootManager::class) && !file_exists($autoloader)) {
  add_action('admin_notices', static function (): void {
    echo '<div class="notice notice-error"><p>';
    esc_html_e('CtrlField: Composer dependencies are missing. Run "composer install" in the plugin directory.', 'ctrlfield');
    echo '</p></div>';
  });
  return;
}

if (file_exists($autoloader)) {
  require_once $autoloader;
}

// Prefixed third-party dependencies (illuminate/*, symfony/*, carbon, …) are
// scoped under CtrlField\Vendor\ so they can't clash with another plugin's copy.
// Present in the built zip; absent when running from source before `composer scope`.
$prefixedAutoloader = CTRLFIELD_PATH . 'vendor-prefixed/autoload.php';
if (file_exists($prefixedAutoloader)) {
  require_once $prefixedAutoloader;
}

// Global template helper functions (ctrlfield_get(), ctrlfield_render(), …).
// Required here, inside WordPress, rather than via Composer's files autoload: the
// file guards against direct access with exit, which would abort Composer-based
// tooling (phpunit, phpstan) that loads the autoloader outside WordPress.
require_once CTRLFIELD_PATH . 'src/Integrations/Blade/helpers.php';

add_action('init', static function (): void {
  load_plugin_textdomain('ctrlfield', false, dirname(plugin_basename(CTRLFIELD_FILE)) . '/languages');
}, 0);

add_action('plugins_loaded', static function (): void {
  \CtrlField\Bootstrap\BootManager::boot();
});

// Pro features (pro/) are unlocked by a license. The public Free build ships without pro/.
if (is_readable(CTRLFIELD_PATH . 'pro/bootstrap.php')) {
  require_once CTRLFIELD_PATH . 'pro/bootstrap.php';
}
