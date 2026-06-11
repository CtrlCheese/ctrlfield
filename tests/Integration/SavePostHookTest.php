<?php

declare(strict_types=1);

namespace FieldForge\Tests\Integration;

use FieldForge\Core\Cache\CacheAdapter;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;
use WP_UnitTestCase;

/**
 * Integration: save_post hook writes field data to _fieldforge_data.
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
        $this->adapter = new PostMetaAdapter(new WpPostMetaDriver());
    }

    // -------------------------------------------------------------------------
    // Acceptance: save_post writes to _fieldforge_data
    // -------------------------------------------------------------------------

    public function test_save_post_persists_field_payload(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        // Build a valid fieldforge_payload
        $payload = json_encode([
            'title_extra' => 'Integration Test',
            'score'       => 42,
            'hidden_field' => '',
        ]);

        // Simulate the form POST — set up $_POST with nonce and payload
        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = $payload;

        // Trigger save_post (MetaBoxServiceProvider has this hooked)
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();
        $stored = $this->adapter->load($postId);

        $this->assertIsArray($stored, '_fieldforge_data should contain an array after save.');
        $this->assertSame('Integration Test', $stored['title_extra']);
        $this->assertSame(42, $stored['score']);
    }

    // -------------------------------------------------------------------------
    // Acceptance: stored in _fieldforge_data, NOT a separate field per key
    // -------------------------------------------------------------------------

    public function test_data_stored_in_single_json_blob_key(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Blob Test', 'score' => 0, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $rawMeta = get_post_meta($postId, PostMetaAdapter::META_KEY, true);
        $this->assertIsString($rawMeta, '_fieldforge_data must be a single JSON string.');

        $decoded = json_decode($rawMeta, true);
        $this->assertArrayHasKey('schema_version', $decoded);
        $this->assertArrayHasKey('fields', $decoded);
    }

    // -------------------------------------------------------------------------
    // Acceptance: setIndex(true) field written as separate meta row
    // -------------------------------------------------------------------------

    public function test_indexed_field_written_to_idx_meta_key(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Indexed', 'score' => 99, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $indexedValue = get_post_meta($postId, PostMetaAdapter::INDEX_KEY_PREFIX . 'score', true);
        $this->assertSame('99', (string) $indexedValue, 'Indexed field must exist as a separate meta row.');
    }

    // -------------------------------------------------------------------------
    // Acceptance: meta_query works on indexed fields
    // -------------------------------------------------------------------------

    public function test_wp_query_meta_query_finds_indexed_field(): void
    {
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = json_encode(['title_extra' => 'Queryable', 'score' => 77, 'hidden_field' => '']);

        do_action('save_post', $postId, get_post($postId), true);

        $query = new \WP_Query([
            'post_type'  => 'ff_test_post',
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
        $postId = $this->factory->post->create(['post_type' => 'ff_test_post']);

        // First save: include hidden_field value
        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = json_encode([
            'title_extra'  => 'Preserving',
            'score'        => 0,
            'hidden_field' => 'Secret value',
        ]);
        do_action('save_post', $postId, get_post($postId), true);

        CacheAdapter::flush();

        // Second save: hidden_field ABSENT from payload (field is hidden in UI)
        $_POST['fieldforge_nonce']   = wp_create_nonce('fieldforge_save_' . $postId);
        $_POST['fieldforge_payload'] = json_encode([
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
        unset($_POST['fieldforge_nonce'], $_POST['fieldforge_payload']);
        parent::tearDown();
    }
}
