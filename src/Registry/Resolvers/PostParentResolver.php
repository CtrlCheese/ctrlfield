<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('post_parent', '==', 42) — the post's direct parent id */
final class PostParentResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        $post = get_post($screen->postId);

        return $post instanceof \WP_Post && PostRuleOperator::compare($operator, (int) $post->post_parent === (int) $value);
    }
}
