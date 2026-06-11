<?php

declare(strict_types=1);

use FieldForge\Data\FieldDataService;

if (! function_exists('fieldforge_get')) {
    function fieldforge_get(string $key, ?int $postId = null): mixed
    {
        return fieldforge_get_all($postId)[$key] ?? null;
    }
}

if (! function_exists('fieldforge_get_all')) {
    /**
     * Returns all stored field values for a post.
     *
     * @return array<string, mixed>
     */
    function fieldforge_get_all(?int $postId = null): array
    {
        $id = $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0);

        if ($id <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($id, 'post');
    }
}

if (! function_exists('fieldforge_get_user')) {
    function fieldforge_get_user(string $key, int $userId = 0): mixed
    {
        return fieldforge_get_all_user($userId)[$key] ?? null;
    }
}

if (! function_exists('fieldforge_get_all_user')) {
    /**
     * Returns all stored field values for a user.
     *
     * @return array<string, mixed>
     */
    function fieldforge_get_all_user(int $userId = 0): array
    {
        $id = $userId > 0 ? $userId : (function_exists('get_current_user_id') ? get_current_user_id() : 0);

        if ($id <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($id, 'user');
    }
}

if (! function_exists('fieldforge_get_comment')) {
    function fieldforge_get_comment(string $key, int $commentId = 0): mixed
    {
        if ($commentId <= 0) {
            return null;
        }

        return FieldDataService::getInstance()->get($key, $commentId, 'comment');
    }
}

if (! function_exists('fieldforge_get_all_comment')) {
    /**
     * Returns all stored field values for a comment.
     *
     * @return array<string, mixed>
     */
    function fieldforge_get_all_comment(int $commentId): array
    {
        if ($commentId <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($commentId, 'comment');
    }
}

if (! function_exists('fieldforge_get_term')) {
    function fieldforge_get_term(string $key, int $termId): mixed
    {
        if ($termId <= 0) {
            return null;
        }

        return FieldDataService::getInstance()->get($key, $termId, 'term');
    }
}

if (! function_exists('fieldforge_get_all_term')) {
    /**
     * Returns all stored field values for a taxonomy term.
     *
     * @return array<string, mixed>
     */
    function fieldforge_get_all_term(int $termId): array
    {
        if ($termId <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($termId, 'term');
    }
}

if (! function_exists('fieldforge_get_options')) {
    function fieldforge_get_options(string $key, string $pageSlug): mixed
    {
        return FieldDataService::getInstance()->get($key, $pageSlug, 'options');
    }
}

if (! function_exists('fieldforge_get_all_options')) {
    /**
     * Returns all stored field values for an options page.
     *
     * @return array<string, mixed>
     */
    function fieldforge_get_all_options(string $pageSlug): array
    {
        return FieldDataService::getInstance()->getAll($pageSlug, 'options');
    }
}

// ── Theme integration helpers ─────────────────────────────────────────────────
// These are the FieldForge equivalents of CF3's site_option() and
// Flynt's Options::get() — designed to be called from Blade templates
// and theme PHP files without knowing FieldForge internals.

if (! function_exists('ff_option')) {
    /**
     * Read a single value from any options page.
     * Equivalent to CF3's carbon_get_theme_option() / site_option().
     *
     * Usage in Blade:   {{ ff_option('social_instagram', 'theme_options') }}
     * Usage in PHP:     $logo = ff_option('site_logo', 'theme_options');
     */
    function ff_option(string $key, string $pageSlug, mixed $default = null): mixed
    {
        return fieldforge_get_options($key, $pageSlug) ?? $default;
    }
}

if (! function_exists('fieldforge_load_schemas')) {
    /**
     * Load all *.php schema files from a directory.
     * Call this from the theme's functions.php on the 'init' hook (priority < 10).
     *
     * Usage:
     *   add_action('init', function() {
     *       fieldforge_load_schemas(get_template_directory() . '/fieldforge/');
     *   }, 5);
     */
    function fieldforge_load_schemas(string $absolutePath): void
    {
        if (! class_exists(\FieldForge\Schema\SchemaLoader::class)) {
            return;
        }
        \FieldForge\Schema\SchemaLoader::loadDirectory($absolutePath);
    }
}

if (! function_exists('fieldforge_load_components')) {
    /**
     * Component-based auto-discovery: scans subdirectories for a fields.php and loads each.
     * Mirrors CF3's FieldGroupRenderer::registerAll() and Flynt's component loading.
     *
     * Usage:
     *   add_action('init', function() {
     *       fieldforge_load_components(get_template_directory() . '/components/');
     *   }, 5);
     */
    function fieldforge_load_components(string $componentsDir, string $fieldsFile = 'fields.php'): void
    {
        if (! class_exists(\FieldForge\Schema\SchemaLoader::class)) {
            return;
        }
        \FieldForge\Schema\SchemaLoader::loadComponentsDirectory($componentsDir, $fieldsFile);
    }
}
