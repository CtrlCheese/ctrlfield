<?php

declare(strict_types=1);

namespace FieldForge\Tests\Integration;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;
use WP_UnitTestCase;

/**
 * Integration: invalid nonce must prevent any data from being written.
 *
 * PRD §3.8: nonce validation is the first pipeline gate.
 */
class NonceRejectionTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        CacheAdapter::flush();
    }

    public function test_invalid_nonce_does_not_write_data(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        $_POST['fieldforge_nonce']   = 'totally_invalid_nonce';
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Injected', 'score' => 0]);

        // Must not throw — pipeline catches nonce failure internally
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $stored  = $adapter->load($postId);

        $this->assertNull($stored, 'Invalid nonce: _fieldforge_data must remain empty.');
    }

    public function test_missing_nonce_does_not_write_data(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        // No nonce in POST — fieldforge_payload present but nonce absent
        unset($_POST['fieldforge_nonce']);
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Injected', 'score' => 0]);

        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();
        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $stored  = $adapter->load($postId);

        $this->assertNull($stored, 'Missing nonce: _fieldforge_data must remain empty.');
    }

    public function tearDown(): void
    {
        unset($_POST['fieldforge_nonce'], $_POST['fieldforge_payload']);
        parent::tearDown();
    }
}
