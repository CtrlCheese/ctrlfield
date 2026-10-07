<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('post', '==', 42) — one specific post */
final class PostResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        return PostRuleOperator::compare($operator, $screen->postId === (int) $value);
    }
}
