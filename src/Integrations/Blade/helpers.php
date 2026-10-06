<?php

declare(strict_types=1);

use CtrlField\Data\FieldDataService;

if (! function_exists('ctrlfield_get')) {
    function ctrlfield_get(string $key, ?int $postId = null): mixed
    {
        $id = $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0);

        if ($id <= 0) {
            return null;
        }

        // Applies ->returnFormat() (image array, DateTime, page URL…). ctrlfield_get_all()
        // stays raw; reading through it here silently ignored every return format.
        return FieldDataService::getInstance()->get($key, $id, 'post');
    }
}

if (! function_exists('ctrlfield_get_all')) {
    /**
     * Returns all stored field values for a post.
     *
     * @return array<string, mixed>
     */
    function ctrlfield_get_all(?int $postId = null): array
    {
        $id = $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0);

        if ($id <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($id, 'post');
    }
}

if (! function_exists('ctrlfield_get_user')) {
    function ctrlfield_get_user(string $key, int $userId = 0): mixed
    {
        return ctrlfield_get_all_user($userId)[$key] ?? null;
    }
}

if (! function_exists('ctrlfield_get_all_user')) {
    /**
     * Returns all stored field values for a user.
     *
     * @return array<string, mixed>
     */
    function ctrlfield_get_all_user(int $userId = 0): array
    {
        $id = $userId > 0 ? $userId : (function_exists('get_current_user_id') ? get_current_user_id() : 0);

        if ($id <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($id, 'user');
    }
}

if (! function_exists('ctrlfield_get_comment')) {
    function ctrlfield_get_comment(string $key, int $commentId = 0): mixed
    {
        if ($commentId <= 0) {
            return null;
        }

        return FieldDataService::getInstance()->get($key, $commentId, 'comment');
    }
}

if (! function_exists('ctrlfield_get_all_comment')) {
    /**
     * Returns all stored field values for a comment.
     *
     * @return array<string, mixed>
     */
    function ctrlfield_get_all_comment(int $commentId): array
    {
        if ($commentId <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($commentId, 'comment');
    }
}

if (! function_exists('ctrlfield_get_term')) {
    function ctrlfield_get_term(string $key, int $termId): mixed
    {
        if ($termId <= 0) {
            return null;
        }

        return FieldDataService::getInstance()->get($key, $termId, 'term');
    }
}

if (! function_exists('ctrlfield_get_all_term')) {
    /**
     * Returns all stored field values for a taxonomy term.
     *
     * @return array<string, mixed>
     */
    function ctrlfield_get_all_term(int $termId): array
    {
        if ($termId <= 0) {
            return [];
        }

        return FieldDataService::getInstance()->getAll($termId, 'term');
    }
}

if (! function_exists('ctrlfield_get_options')) {
    function ctrlfield_get_options(string $key, string $pageSlug): mixed
    {
        return FieldDataService::getInstance()->get($key, $pageSlug, 'options');
    }
}

if (! function_exists('ctrlfield_get_all_options')) {
    /**
     * Returns all stored field values for an options page.
     *
     * @return array<string, mixed>
     */
    function ctrlfield_get_all_options(string $pageSlug): array
    {
        return FieldDataService::getInstance()->getAll($pageSlug, 'options');
    }
}

// ── Theme integration helpers ─────────────────────────────────────────────────
// These are the CtrlField equivalents of CF3's site_option() and
// Flynt's Options::get() — designed to be called from Blade templates
// and theme PHP files without knowing CtrlField internals.

if (! function_exists('ctrlf_option')) {
    /**
     * Read a single value from any options page.
     * Equivalent to CF3's carbon_get_theme_option() / site_option().
     *
     * Usage in Blade:   {{ ctrlf_option('social_instagram', 'theme_options') }}
     * Usage in PHP:     $logo = ctrlf_option('site_logo', 'theme_options');
     */
    function ctrlf_option(string $key, string $pageSlug, mixed $default = null): mixed
    {
        return ctrlfield_get_options($key, $pageSlug) ?? $default;
    }
}

if (! function_exists('ctrlfield_load_schemas')) {
    /**
     * Load all *.php schema files from a directory.
     * Call this from the theme's functions.php on the 'init' hook (priority < 10).
     *
     * Usage:
     *   add_action('init', function() {
     *       ctrlfield_load_schemas(get_template_directory() . '/ctrlfield/');
     *   }, 5);
     */
    function ctrlfield_load_schemas(string $absolutePath): void
    {
        if (! class_exists(\CtrlField\Schema\SchemaLoader::class)) {
            return;
        }
        \CtrlField\Schema\SchemaLoader::loadDirectory($absolutePath);
    }
}

if (! function_exists('ctrlfield_load_components')) {
    /**
     * Component-based auto-discovery: scans subdirectories for a fields.php and loads each.
     * Mirrors CF3's FieldGroupRenderer::registerAll() and Flynt's component loading.
     *
     * Usage:
     *   add_action('init', function() {
     *       ctrlfield_load_components(get_template_directory() . '/components/');
     *   }, 5);
     */
    function ctrlfield_load_components(string $componentsDir, string $fieldsFile = 'fields.php'): void
    {
        if (! class_exists(\CtrlField\Schema\SchemaLoader::class)) {
            return;
        }
        \CtrlField\Schema\SchemaLoader::loadComponentsDirectory($componentsDir, $fieldsFile);
    }
}

// ── Component rendering helpers ───────────────────────────────────────────────
// Engine-agnostic helpers for rendering flexible-content components from PHP
// templates. These wrap ComponentRenderer so themes don't need to import it.

if (! function_exists('ctrlfield_render_component')) {
    /**
     * Renders a flexible-content section array and echoes the result.
     *
     * The $section array must contain a '_layout' key that matches a registered
     * component key.
     *
     * Usage (PHP template):
     *   <?php foreach (ctrlfield_get('page_builder') as $section): ?>
     *       <?php ctrlfield_render_component($section); ?>
     *   <?php endforeach; ?>
     *
     * @param array<string, mixed> $section
     */
    function ctrlfield_render_component(array $section): void
    {
        echo \CtrlField\Components\ComponentRenderer::render($section);
    }
}

if (! function_exists('ctrlfield_render_component_string')) {
    /**
     * Renders a flexible-content section array and returns the HTML string.
     *
     * @param array<string, mixed> $section
     */
    function ctrlfield_render_component_string(array $section): string
    {
        return \CtrlField\Components\ComponentRenderer::render($section);
    }
}

if (! function_exists('ctrlfield_render')) {
    /**
     * Renders a named component with explicit data and echoes the result.
     *
     * Usage (PHP template):
     *   <?php ctrlfield_render('hero', ['headline' => 'Welcome']); ?>
     *
     * @param array<string, mixed> $data
     */
    function ctrlfield_render(string $componentName, array $data = []): void
    {
        echo \CtrlField\Components\ComponentRenderer::renderByName($componentName, $data);
    }
}

// Relationship, Post Object and Map helpers (Free since the ACF-parity split).

if (! function_exists('ctrlfield_get_relationship')) {
    /**
     * Returns an array of WP_Post objects for a relationship field.
     *
     * @return array<int, \WP_Post>
     */
    function ctrlfield_get_relationship(string $fieldKey, int $postId = 0): array
    {
        $ids   = ctrlfield_get_relationship_ids($fieldKey, $postId);
        $posts = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post instanceof \WP_Post) {
                $posts[] = $post;
            }
        }
        return $posts;
    }
}

if (! function_exists('ctrlfield_get_relationship_ids')) {
    /**
     * Returns an array of related post IDs for a relationship field.
     *
     * @return array<int, int>
     */
    function ctrlfield_get_relationship_ids(string $fieldKey, int $postId = 0): array
    {
        global $wpdb;

        if ($postId === 0) {
            $postId = get_the_ID() ?: 0;
        }

        if ($postId === 0) {
            return [];
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
            return [];
        }

        $table    = $wpdb->prefix . 'ctrlf_rel_' . $fieldKey;
        $rows     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT target_id FROM `{$table}` WHERE source_id = %d AND field_key = %s ORDER BY sort_order ASC",
                $postId,
                $fieldKey,
            ),
            ARRAY_A,
        );

        return is_array($rows) ? array_map(static fn (array $r) => (int) $r['target_id'], $rows) : [];
    }
}

if (! function_exists('ctrlfield_get_map')) {
    /**
     * Returns the map data array for a map field.
     *
     * @return array{lat: float, lng: float, zoom: int, address: string}
     */
    function ctrlfield_get_map(string $key, int $postId = 0): array
    {
        $value = ctrlfield_get($key, $postId ?: null);

        if (! is_array($value)) {
            return ['lat' => 0.0, 'lng' => 0.0, 'zoom' => 14, 'address' => ''];
        }

        return [
            'lat'     => is_numeric($value['lat'] ?? null)  ? (float) $value['lat']  : 0.0,
            'lng'     => is_numeric($value['lng'] ?? null)  ? (float) $value['lng']  : 0.0,
            'zoom'    => isset($value['zoom'])  ? (int) $value['zoom']  : 14,
            'address' => (string) ($value['address'] ?? ''),
        ];
    }
}

if (! function_exists('ctrlfield_get_map_embed')) {
    /**
     * Returns an HTML embed for a map field.
     * Full implementation deferred to v3 (requires server-side API calls per provider).
     *
     * @param  array<string, mixed> $options
     */
    function ctrlfield_get_map_embed(string $key, array $options = [], int $postId = 0): string
    {
        $data = ctrlfield_get_map($key, $postId);

        if ($data['lat'] === 0.0 && $data['lng'] === 0.0) {
            return '';
        }

        $lat    = $data['lat'];
        $lng    = $data['lng'];
        $zoom   = $data['zoom'];
        $height = (int) ($options['height'] ?? 400);

        // OpenStreetMap iframe — no API key required, works for all providers as fallback
        $src = sprintf(
            'https://www.openstreetmap.org/export/embed.html?bbox=%s,%s,%s,%s&layer=mapnik&marker=%s,%s',
            $lng - 0.01, $lat - 0.01, $lng + 0.01, $lat + 0.01,
            $lat, $lng,
        );

        return sprintf(
            '<iframe src="%s" width="100%%" height="%d" style="border:0" loading="lazy" title="%s"></iframe>',
            esc_url($src),
            $height,
            esc_attr($data['address'] ?: 'Map'),
        );
    }
}

if (! function_exists('ctrlfield_get_related_by')) {
    /**
     * Reverse lookup: returns WP_Post objects that reference the given target post.
     *
     * @return array<int, \WP_Post>
     */
    function ctrlfield_get_related_by(string $fieldKey, int $targetId): array
    {
        global $wpdb;

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
            return [];
        }

        $table = $wpdb->prefix . 'ctrlf_rel_' . $fieldKey;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source_id FROM `{$table}` WHERE target_id = %d AND field_key = %s ORDER BY sort_order ASC",
                $targetId,
                $fieldKey,
            ),
            ARRAY_A,
        );

        if (! is_array($rows)) {
            return [];
        }

        $posts = [];
        foreach ($rows as $row) {
            $post = get_post((int) $row['source_id']);
            if ($post instanceof \WP_Post) {
                $posts[] = $post;
            }
        }
        return $posts;
    }
}
