<?php

declare(strict_types=1);

namespace CtrlField\Storage;

use CtrlField\Storage\Drivers\WpPostMetaDriver;

/**
 * Field history in post revisions: each revision carries the fields as they
 * were when it was made, and restoring a revision brings them back.
 *
 * WordPress creates the revision before the fields are saved (and the block
 * editor saves meta boxes in a second request), so the revision first gets
 * the fields as they are, and is updated once the new values are saved.
 * Revisions get no index rows (_ctrlfield_idx_*): they are never queried.
 */
final class RevisionSnapshots
{
    /** A revision made this long ago or less belongs to the save in progress. */
    private const SAME_SAVE_SECONDS = 120;

    public function __construct(private readonly PostMetaAdapter $adapter) {}

    public static function make(): self
    {
        return new self(new PostMetaAdapter(new WpPostMetaDriver()));
    }

    /** _wp_put_post_revision: copy the parent's fields onto the new revision. */
    public function onRevisionCreated(int $revisionId): void
    {
        $parentId = (int) wp_get_post_parent_id($revisionId);
        if ($parentId > 0) {
            $this->copy($parentId, $revisionId);
        }
    }

    /** After the fields of a post were saved: the revision of this save gets the new values. */
    public function onFieldsSaved(int $postId): void
    {
        $revisions = wp_get_post_revisions($postId, ['numberposts' => 1, 'orderby' => 'date ID', 'order' => 'DESC']);
        $latest    = reset($revisions);
        if (! $latest instanceof \WP_Post) {
            return;
        }

        $made = (int) strtotime($latest->post_date_gmt . ' UTC');
        if ($made > 0 && time() - $made <= self::SAME_SAVE_SECONDS) {
            $this->copy($postId, $latest->ID);
        }
    }

    /** wp_restore_post_revision: the revision's fields become the post's fields. */
    public function onRevisionRestored(int $postId, int $revisionId): void
    {
        $this->copy($revisionId, $postId);
    }

    /**
     * Copies the stored fields as they are — raw, so data saved under an older
     * schema version is not lost on the way.
     */
    private function copy(int $from, int $to): void
    {
        $raw = $this->adapter->loadRaw($from);
        if ($raw === null || $raw['fields'] === []) {
            return;
        }

        $this->adapter->save($to, $raw['fields'], $raw['schema_version']);
    }
}
