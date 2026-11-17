<?php

declare(strict_types=1);

// Autoload production code
require_once __DIR__ . '/../../vendor/autoload.php';

// Scoped third-party dependencies (CtrlField\Vendor\…), when the build has run.
$prefixed = __DIR__ . '/../../vendor-prefixed/autoload.php';
if (file_exists($prefixed)) {
    require_once $prefixed;
}

// WP function stubs needed by admin renderers under test
require_once __DIR__ . '/stubs.php';
