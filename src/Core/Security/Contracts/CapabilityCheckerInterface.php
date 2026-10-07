<?php

declare(strict_types=1);

namespace CtrlField\Core\Security\Contracts;

interface CapabilityCheckerInterface
{
    /** @param int ...$args e.g. a post id for meta capabilities ('edit_post', 42). */
    public function currentUserCan(string $capability, int ...$args): bool;
}
