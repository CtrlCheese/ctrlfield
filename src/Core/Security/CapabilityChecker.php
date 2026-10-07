<?php

declare(strict_types=1);

namespace CtrlField\Core\Security;

use CtrlField\Core\Security\Contracts\CapabilityCheckerInterface;

/**
 * Production wrapper around current_user_can().
 * Excluded from PHPStan — WP functions unavailable outside WP runtime.
 */
class CapabilityChecker implements CapabilityCheckerInterface
{
    public function currentUserCan(string $capability, int ...$args): bool
    {
        return (bool) current_user_can($capability, ...$args);
    }
}
