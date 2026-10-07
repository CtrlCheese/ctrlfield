<?php

declare(strict_types=1);

namespace CtrlField\Registry\Resolvers;

use CtrlField\Builder\AdminContext;
use CtrlField\Registry\Contracts\ContextInterface;

/** where('page_type', '==', 'front_page' | 'posts_page' | 'top_level' | 'parent' | 'child') */
final class PageTypeResolver implements ContextInterface
{
    public static function matches(mixed $screen, string $operator, mixed $value): bool
    {
        if (! $screen instanceof AdminContext || $screen->postId === null) {
            return false; // no concrete post: a rule about a post cannot match
        }

        $post = get_post($screen->postId);
        if (! $post instanceof \WP_Post) {
            return false;
        }

        $is = match ((string) $value) {
            'front_page' => get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $post->ID,
            'posts_page' => get_option('show_on_front') === 'page' && (int) get_option('page_for_posts') === $post->ID,
            'top_level'  => (int) $post->post_parent === 0,
            'child'      => (int) $post->post_parent > 0,
            'parent'     => get_children(['post_parent' => $post->ID, 'post_type' => $post->post_type, 'numberposts' => 1, 'fields' => 'ids']) !== [],
            default      => false,
        };

        return PostRuleOperator::compare($operator, $is);
    }
}
