<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('post_term', '==', 'category:news') or a term id — the post has that term */
final class PostTermResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        if (is_numeric($value)) {
            $term = get_term((int) $value);
            $has  = $term instanceof \WP_Term
                && has_term($term->term_id, $term->taxonomy, $screen->postId);
        } else {
            [$taxonomy, $slug] = array_pad(explode(':', (string) $value, 2), 2, '');
            $has = $taxonomy !== '' && $slug !== '' && has_term($slug, $taxonomy, $screen->postId);
        }

        return PostRuleOperator::compare($operator, $has);
    }
}
