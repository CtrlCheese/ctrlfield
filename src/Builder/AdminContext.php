<?php

declare(strict_types=1);

namespace CtrlField\Builder;

/**
 * Represents the current WordPress admin screen context.
 *
 * Constructed directly in unit tests and from WP_Screen in production.
 *
 *  - taxonomy:    slug of the current taxonomy screen (term add/edit)
 *  - contextType: 'user_profile' | 'comment' for non-post, non-taxonomy screens
 *  - postId:      the post being edited — needed by post rules (template, parent, term…)
 *  - userId:      the user being edited — needed by user_role
 *
 * Without postId / userId, rules about a specific post or user do not match.
 */
final class AdminContext
{
    public function __construct(
        public readonly ?string $postType    = null,
        public readonly ?string $optionsPage = null,
        public readonly ?string $taxonomy    = null,
        public readonly ?string $contextType = null,
        public readonly ?int    $postId      = null,
        public readonly ?int    $userId      = null,
    ) {}

    /** Context for editing / saving one post: its type plus the post itself. */
    public static function forPost(int $postId): self
    {
        $type = function_exists('get_post_type') ? get_post_type($postId) : false;

        return new self(postType: is_string($type) ? $type : null, postId: $postId > 0 ? $postId : null);
    }

    /** Context for a user profile screen / save. */
    public static function forUser(int $userId): self
    {
        return new self(contextType: 'user_profile', userId: $userId > 0 ? $userId : null);
    }
}
