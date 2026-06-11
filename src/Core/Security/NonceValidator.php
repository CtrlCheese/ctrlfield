<?php

declare(strict_types=1);

namespace FieldForge\Core\Security;

use FieldForge\Core\Security\Contracts\NonceValidatorInterface;

/**
 * Production wrapper around wp_verify_nonce().
 * Excluded from PHPStan — WP functions unavailable outside WP runtime.
 */
class NonceValidator implements NonceValidatorInterface
{
    public function verify(string $nonce, string $action): bool
    {
        return (bool) wp_verify_nonce($nonce, $action);
    }
}
