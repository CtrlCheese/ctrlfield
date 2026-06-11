<?php

declare(strict_types=1);

namespace FieldForge\Tests\Integration;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;
use WP_UnitTestCase;

/**
 * Integration: autosave requests must NOT write to _fieldforge_data.
 *
 * PRD §3.8: "wp_is_post_autosave($postId) === false" is a required guard.
 */
class AutosaveTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        CacheAdapter::flush();
    }

    public function test_autosave_does_not_write_fieldforge_data(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        // Create an autosave revision
        $autosaveId = wp_create_post_autosave([
            'post_ID'      => $postId,
            'post_type'    => 'ff_test_post',
            'post_content' => 'Autosave content',
            'post_title'   => 'Autosave',
            'post_status'  => 'auto-draft',
        ]);

        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $autosaveId);
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Should NOT save', 'score' => 0]);

        // Fire save_post for the autosave ID
        do_action('save_post', $autosaveId, get_post($autosaveId), true);

        CacheAdapter::flush();
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $stored  = $adapter->load($autosaveId);

        $this->assertNull($stored, 'Autosave must never write to _fieldforge_data.');
    }

    public function tearDown(): void
    {
        unset($_POST['fieldforge_nonce'], $_POST['fieldforge_payload']);
        parent::tearDown();
    }
}
