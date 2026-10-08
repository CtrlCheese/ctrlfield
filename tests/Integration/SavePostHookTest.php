<?php

declare(strict_types=1);

namespace CtrlField\Tests\Integration;

use CtrlField\Core\Cache\CacheAdapter;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;
use WP_UnitTestCase;

/**
 * Integration: save_post hook writes field data to _ctrlfield_data.
 *
 * Run with:
 *   npx @wordpress/env run tests-cli vendor/bin/phpunit \
 *       --configuration phpunit-integration.xml \
 *       --filter SavePostHookTest
 */
class SavePostHookTest extends WP_UnitTestCase
{
    private PostMetaAdapter $adapter;

    public function setUp(): void
    {
        parent::setUp();
        CacheAdapter::flush();
        // An editor-capable user: what is tested is the nonce / autosave / save path, not permissions.
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->adapter = new PostMetaAdapter(new WpPostMetaDriver());
    }

    // -------------------------------------------------------------------------
    // Acceptance: save_post writes to _ctrlfield_data
    // -------------------------------------------------------------------------

    public function test_save_post_persists_field_payload(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        // Build a valid ctrlfield_payload
        $payload = json_encode([
            'title_extra' => 'Integration Test',
            'score'       => 42,
            'hidden_field' => '',
        ]);

        // Simulate the form POST — set up $_POST with nonce and payload
        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = $payload;

        // Trigger save_post (MetaBoxServiceProvider has this hooked)
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();
        $stored = $this->adapter->load($postId);

        $this->assertIsArray($stored, '_ctrlfield_data should contain an array after save.');
        $this->assertSame('Integration Test', $stored['title_extra']);
        $this->assertSame(42, $stored['score']);
    }

    // -------------------------------------------------------------------------
    // Acceptance: stored in _ctrlfield_data, NOT a separate field per key
    // -------------------------------------------------------------------------

    public function test_data_stored_in_single_json_blob_key(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode(['title_extra' => 'Blob Test', 'score' => 0, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $rawMeta = get_post_meta($postId, PostMetaAdapter::META_KEY, true);
        $this->assertIsString($rawMeta, '_ctrlfield_data must be a single JSON string.');

        $decoded = json_decode($rawMeta, true);
        $this->assertArrayHasKey('schema_version', $decoded);
        $this->assertArrayHasKey('fields', $decoded);
    }

    // -------------------------------------------------------------------------
    // Acceptance: setIndex(true) field written as separate meta row
    // -------------------------------------------------------------------------

    public function test_indexed_field_written_to_idx_meta_key(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode(['title_extra' => 'Indexed', 'score' => 99, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $indexedValue = get_post_meta($postId, PostMetaAdapter::INDEX_KEY_PREFIX . 'score', true);
        $this->assertSame('99', (string) $indexedValue, 'Indexed field must exist as a separate meta row.');
    }

    // -------------------------------------------------------------------------
    // Acceptance: meta_query works on indexed fields
    // -------------------------------------------------------------------------

    public function test_wp_query_meta_query_finds_indexed_field(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode(['title_extra' => 'Queryable', 'score' => 77, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $query = new \WP_Query([
            'post_type'  => 'ctrlf_test_post',
            'meta_query' => [[
                'key'     => PostMetaAdapter::INDEX_KEY_PREFIX . 'score',
                'value'   => 77,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ]],
            'fields'     => 'ids',
        ]);

        $this->assertContains($postId, $query->posts);
    }

    // -------------------------------------------------------------------------
    // Acceptance: visible_when hidden field value is preserved after save
    // -------------------------------------------------------------------------

    public function test_hidden_field_value_preserved_on_save(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ctrlf_test_post']);

        // First save: include hidden_field value
        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode([
            'title_extra'  => 'Preserving',
            'score'        => 0,
            'hidden_field' => 'Secret value',
        ]);
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();

        // Second save: hidden_field ABSENT from payload (field is hidden in UI)
        $_POST['_ctrlfield_nonce']  = wp_create_nonce('ctrlfield_save');
        $_POST['ctrlfield_payload'] = json_encode([
            'title_extra' => 'Updated',
            'score'       => 1,
            // hidden_field intentionally omitted
        ]);
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();
        $stored = $this->adapter->load($postId);

        $this->assertSame('Secret value', $stored['hidden_field'],
            'visible_when hidden fields must be preserved in JSON, never deleted on save.');
    }

    public function tearDown(): void
    {
        unset($_POST['_ctrlfield_nonce'], $_POST['ctrlfield_payload']);
        parent::tearDown();
    }
}
