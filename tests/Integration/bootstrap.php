<?php

/**
 * Bootstrap for CtrlField WordPress integration tests.
 *
 * These tests run INSIDE the wp-env Docker container, where WordPress and its
 * test suite are already installed. Run them with:
 *
 *   npx @wordpress/env run tests-cli vendor/bin/phpunit \
 *       --configuration phpunit-integration.xml
 *
 * Or to install WP tests manually on a local install:
 *   bash bin/install-wp-tests.sh <db_name> <db_user> <db_pass> [db_host]
 *   vendor/bin/phpunit --configuration phpunit-integration.xml
 */

declare(strict_types=1);

// Resolve WP tests directory — set WP_TESTS_DIR env var to override.
$_wpTestsDir = getenv('WP_TESTS_DIR');

if (! $_wpTestsDir) {
    $_wpTestsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (! file_exists($_wpTestsDir . '/includes/functions.php')) {
    echo PHP_EOL;
    echo '  CtrlField Integration Tests' . PHP_EOL;
    echo '  WordPress test library not found at: ' . $_wpTestsDir . PHP_EOL;
    echo '  Run:  bash bin/install-wp-tests.sh  OR  use wp-env (see above).' . PHP_EOL;
    echo PHP_EOL;
    exit(1);
}

// The WP test suite needs the PHPUnit Polyfills (installed for this run, see CI).
if (! defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH') && is_dir(dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

// Load WP tests framework bootstrap functions.
require_once $_wpTestsDir . '/includes/functions.php';

/**
 * Manually load the CtrlField plugin so WP activates it in the test install.
 */
function _ctrlfield_load_plugin(): void
{
    $pluginFile = dirname(__DIR__, 2) . '/ctrlfield.php';
    require $pluginFile;
}
tests_add_filter('muplugins_loaded', '_ctrlfield_load_plugin');

/**
 * Register test-only CPT + field group after WP init so they are available
 * in all integration tests without each test having to re-register.
 */
function _ctrlfield_register_test_schema(): void
{
    \CtrlField\Builder\CPT::make('ctrlf_test_post')
        ->label('Test Post', 'Test Posts')
        ->supports(['title'])
        ->register();

    \CtrlField\Builder\FieldGroup::make('ctrlf_test_group')
        ->where('post_type', '==', 'ctrlf_test_post')
        ->fields([
            \CtrlField\Fields\Field::text('title_extra')->required(),
            \CtrlField\Fields\Field::number('score')->setIndex(true),
            \CtrlField\Fields\Field::text('hidden_field')
                ->visibleWhen('score', '==', 99),
        ])
        ->register();
}
tests_add_filter('init', '_ctrlfield_register_test_schema', 1);

// Start up the WP testing environment.
require $_wpTestsDir . '/includes/bootstrap.php';
