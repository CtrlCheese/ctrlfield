<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('post_status', '==', 'draft') */
final class PostStatusResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        return PostRuleOperator::compare($operator, get_post_status($screen->postId) === (string) $value);
    }
}
