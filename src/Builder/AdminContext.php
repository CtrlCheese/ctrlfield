<?php

declare(strict_types=1);

namespace CtrlField\Builder;

/**
 * Represents the current WordPress admin screen context.
 *
 * Constructed directly in unit tests and from WP_Screen in production.
 *
 * v2 additions:
 *  - taxonomy:    slug of the current taxonomy screen (term add/edit)
 *  - contextType: 'user_profile' | 'comment' for non-post, non-taxonomy screens
 */
final class AdminContext
{
    public function __construct(
        public readonly ?string $postType    = null,
        public readonly ?string $optionsPage = null,
        public readonly ?string $taxonomy    = null,
        public readonly ?string $contextType = null,
    ) {}
}
