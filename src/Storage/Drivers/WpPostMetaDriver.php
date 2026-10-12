<?php

declare(strict_types=1);

namespace CtrlField\Storage\Drivers;

/**
 * Production driver: delegates to WordPress metadata functions.
 * Excluded from PHPStan — WP functions are not available outside WP runtime.
 *
 * update_metadata() / delete_metadata() instead of the *_post_meta() wrappers:
 * those redirect a revision ID to its parent post, so a snapshot meant for a
 * revision overwrote the live post and the revision stayed empty.
 */
class WpPostMetaDriver implements PostMetaDriverInterface
{
    public function update(int $postId, string $key, mixed $value): void
    {
        // update_metadata() unslashes its value; without wp_slash() every \" and
        // \\ in the JSON blob lost its backslash and the whole blob became invalid.
        update_metadata('post', $postId, $key, wp_slash($value));
    }

    public function get(int $postId, string $key): mixed
    {
        return get_metadata('post', $postId, $key, true);
    }

    public function delete(int $postId, string $key): void
    {
        delete_metadata('post', $postId, $key);
    }
}
