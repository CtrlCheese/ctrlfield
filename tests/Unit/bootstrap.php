<?php

declare(strict_types=1);

// Satisfy the ABSPATH direct-access guards in autoloaded files during unit tests.
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

// Autoload production code
require_once __DIR__ . '/../../vendor/autoload.php';

// Scoped third-party dependencies (CtrlField\Vendor\…), when the build has run.
$prefixed = __DIR__ . '/../../vendor-prefixed/autoload.php';
if (file_exists($prefixed)) {
    require_once $prefixed;
}

// Global template helpers — no longer Composer-files-autoloaded, so load them here
// (ABSPATH is defined above, so the file's direct-access guard is a no-op).
require_once __DIR__ . '/../../src/Integrations/Blade/helpers.php';

// WP function stubs needed by admin renderers under test
require_once __DIR__ . '/stubs.php';
