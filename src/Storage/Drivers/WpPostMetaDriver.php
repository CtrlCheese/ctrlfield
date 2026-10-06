<?php

declare(strict_types=1);

namespace CtrlField\Storage\Drivers;

/**
 * Production driver: delegates to WordPress post meta functions.
 * Excluded from PHPStan — WP functions are not available outside WP runtime.
 */
class WpPostMetaDriver implements PostMetaDriverInterface
{
    public function update(int $postId, string $key, mixed $value): void
    {
        update_post_meta($postId, $key, $value);
    }

    public function get(int $postId, string $key): mixed
    {
        return get_post_meta($postId, $key, true);
    }

    public function delete(int $postId, string $key): void
    {
        delete_post_meta($postId, $key);
    }
}
