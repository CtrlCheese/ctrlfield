<?php

declare(strict_types=1);

namespace FieldForge\Core\Security\Contracts;

interface NonceValidatorInterface
{
    public function verify(string $nonce, string $action): bool;
}
