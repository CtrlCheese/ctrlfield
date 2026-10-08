<?php

declare(strict_types=1);

namespace CtrlField\Tests\Integration;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;
use WP_UnitTestCase;

/**
 * Integration: autosave requests must NOT write to _ctrlfield_data.
 *
 * PRD §3.8: "wp_is_post_autosave($postId) === false" is a required guard.
 */
class AutosaveTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        CacheAdapter::flush();
        // An editor-capable user: what is tested is the nonce / autosave / save path, not permissions.
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function test_autosave_does_not_write_ctrlfield_data(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        // Create an autosave revision
        $autosaveId = wp_create_post_autosave([
            'post_ID'      => $postId,
            'post_type'    => 'ctrlf_test_post',
            'post_content' => 'Autosave content',
            'post_title'   => 'Autosave',
            'post_status'  => 'auto-draft',
        ]);

        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode(['title_extra' => 'Should NOT save', 'score' => 0]);

        // Fire save_post for the autosave ID
        do_action('save_post', $autosaveId, get_post($autosaveId), true);

        CacheAdapter::flush();
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $stored  = $adapter->load($autosaveId);

        $this->assertNull($stored, 'Autosave must never write to _ctrlfield_data.');
    }

    public function tearDown(): void
    {
        unset($_POST['_ctrlfield_nonce'], $_POST['ctrlfield_payload']);
        parent::tearDown();
    }
}
