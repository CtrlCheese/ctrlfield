<?php

declare(strict_types=1);

namespace CtrlField\Core\Security\Contracts;

interface CapabilityCheckerInterface
{
    public function currentUserCan(string $capability): bool;
}
