<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('post_format', '==', 'video') — 'standard' when the post has no format */
final class PostFormatResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        $format = get_post_format($screen->postId);

        return PostRuleOperator::compare($operator, ($format === false ? 'standard' : $format) === (string) $value);
    }
}
