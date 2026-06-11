<?php

declare(strict_types=1);

namespace FieldForge\Admin\Gutenberg;

use FieldForge\Builder\AdminContext;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Rest\FieldValueController;

/**
 * Registers the REST routes and conditionally enqueues the Gutenberg React bundle.
 *
 * Excluded from PHPStan — calls WP functions.
 */
class GutenbergBootstrap
{
    public function boot(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
    }

    public function registerRestRoutes(): void
    {
        (new FieldValueController())->register();
    }

    public function enqueueAssets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen === null || ! $screen->is_block_editor()) {
            return;
        }

        $postType = $screen->post_type ?? '';

        if ($postType === '') {
            return;
        }

        $context = new AdminContext(postType: $postType);
        $groups  = ContextRegistry::resolve($context);

        // Do not load sidebar on post types with no registered field groups.
        if (empty($groups)) {
            return;
        }

        $distPath = FIELDFORGE_PATH . 'assets/gutenberg/dist/';
        $assetFile = $distPath . 'index.asset.php';

        $asset = file_exists($assetFile)
            ? (array) require $assetFile
            : ['dependencies' => [], 'version' => FIELDFORGE_VERSION];

        /** @var string[] $dependencies */
        $dependencies = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
        $version      = is_string($asset['version'] ?? null) ? $asset['version'] : FIELDFORGE_VERSION;

        wp_enqueue_script(
            'fieldforge-gutenberg',
            FIELDFORGE_URL . 'assets/gutenberg/dist/index.js',
            $dependencies,
            $version,
            true,
        );

        wp_localize_script('fieldforge-gutenberg', 'fieldforgeGutenberg', [
            'restBase' => rest_url(FieldValueController::NAMESPACE . '/editor/post/'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'postType' => $postType,
        ]);
    }
}
