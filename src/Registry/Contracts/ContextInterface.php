<?php

declare(strict_types=1);

namespace CtrlField\Registry\Contracts;

use CtrlField\Builder\AdminContext;

interface ContextInterface
{
    /**
     * Return true if the given AdminContext matches this resolver's condition.
     *
     * @param mixed  $screen   Always AdminContext in v2.
     * @param string $operator Always '==' or '!=' (where() only supports these).
     * @param mixed  $value    Right-hand side value from ->where().
     */
    public static function matches(mixed $screen, string $operator, mixed $value): bool;
}
