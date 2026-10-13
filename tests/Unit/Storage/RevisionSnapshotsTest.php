<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Storage;

use CtrlField\Storage\PostMetaAdapter;
use CtrlField\Storage\RevisionSnapshots;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PostMetaAdapterTest.php'; // InMemoryPostMetaDriver

/** C7.9: field history in revisions — on the revision, never on the parent. */
final class RevisionSnapshotsTest extends TestCase
{
    private InMemoryPostMetaDriver $driver;
    private PostMetaAdapter $adapter;
    private RevisionSnapshots $revisions;

    protected function setUp(): void
    {
        $this->driver    = new InMemoryPostMetaDriver();
        $this->adapter   = new PostMetaAdapter($this->driver);
        $this->revisions = new RevisionSnapshots($this->adapter);

        $GLOBALS['_wp_posts'] = [
            10 => $this->post(10, 0),
            11 => $this->post(11, 10, gmdate('Y-m-d H:i:s')),             // made by this save
            9  => $this->post(9, 10, gmdate('Y-m-d H:i:s', time() - 3600)), // an older one
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_posts'], $GLOBALS['_wp_revisions']);
    }

    public function test_a_new_revision_gets_the_parents_fields(): void
    {
        $this->adapter->save(10, ['title' => 'Old'], 1);

        $this->revisions->onRevisionCreated(11);

        $this->assertSame(['title' => 'Old'], $this->adapter->load(11));
        $this->assertSame(['title' => 'Old'], $this->adapter->load(10));
    }

    public function test_the_revision_of_this_save_is_updated_with_the_new_values(): void
    {
        $this->adapter->save(10, ['title' => 'Old'], 1);
        $this->revisions->onRevisionCreated(11);   // WordPress: before the fields are saved
        $this->adapter->save(10, ['title' => 'New'], 1);
        $GLOBALS['_wp_revisions'][10] = [11 => $GLOBALS['_wp_posts'][11], 9 => $GLOBALS['_wp_posts'][9]];

        $this->revisions->onFieldsSaved(10);

        $this->assertSame(['title' => 'New'], $this->adapter->load(11));
    }

    public function test_an_older_revision_is_left_alone(): void
    {
        $this->adapter->save(9, ['title' => 'Back then'], 1);
        $this->adapter->save(10, ['title' => 'Now'], 1);
        $GLOBALS['_wp_revisions'][10] = [9 => $GLOBALS['_wp_posts'][9]]; // no revision for this save

        $this->revisions->onFieldsSaved(10);

        $this->assertSame(['title' => 'Back then'], $this->adapter->load(9));
    }

    public function test_restoring_brings_the_fields_back_even_with_an_old_schema_version(): void
    {
        $this->driver->update(11, PostMetaAdapter::META_KEY, json_encode(['schema_version' => 0, 'fields' => ['title' => 'Old']]));
        $this->adapter->save(10, ['title' => 'New'], 1);

        $this->revisions->onRevisionRestored(10, 11);

        $raw = $this->adapter->loadRaw(10);
        $this->assertSame(['title' => 'Old'], $raw['fields']);
        $this->assertSame(0, $raw['schema_version']); // migrated on read like any old post
    }

    public function test_restoring_rewrites_the_index_rows_and_revisions_get_none(): void
    {
        \CtrlField\Registry\FieldRegistry::reset();
        \CtrlField\Builder\FieldGroup::make('g')->fields([\CtrlField\Fields\Field::text('status')->setIndex(), \CtrlField\Fields\Field::text('title')])->register();
        $this->adapter->save(10, ['status' => 'draft', 'title' => 'New'], 1, ['status' => 'draft']);
        $this->adapter->save(11, ['status' => 'live', 'title' => 'Old'], 1);

        $this->revisions->onRevisionCreated(9);
        $this->assertSame('', $this->driver->get(9, PostMetaAdapter::INDEX_KEY_PREFIX . 'status'));

        $this->revisions->onRevisionRestored(10, 11);

        $this->assertSame('live', $this->driver->get(10, PostMetaAdapter::INDEX_KEY_PREFIX . 'status'));
        $this->assertSame('', $this->driver->get(10, PostMetaAdapter::INDEX_KEY_PREFIX . 'title'));
        \CtrlField\Registry\FieldRegistry::reset();
    }

    private function post(int $id, int $parent, string $dateGmt = ''): \WP_Post
    {
        $post                = new \WP_Post();
        $post->ID            = $id;
        $post->post_parent   = $parent;
        $post->post_date_gmt = $dateGmt;
        $post->post_type     = $parent > 0 ? 'revision' : 'page';

        return $post;
    }
}
