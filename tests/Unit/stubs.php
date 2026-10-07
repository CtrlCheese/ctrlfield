<?php

/**
 * Minimal WordPress function stubs for unit tests.
 * These are simplified versions that satisfy unit test assertions
 * without requiring a full WordPress environment.
 */

declare(strict_types=1);

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): true
    {
        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): true
    {
        return true;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void {}
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (! function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp = 0): string
    {
        return date($format, $timestamp ?: time());
    }
}

if (! function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $id, mixed $size): false|string
    {
        return false;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $cap, mixed ...$args): bool
    {
        return false;
    }
}

if (! function_exists('wp_die')) {
    function wp_die(mixed $message = ''): never
    {
        throw new \RuntimeException((string) $message);
    }
}

if (! function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, string $icon_url = '', ?int $position = null): string
    {
        return '';
    }
}

if (! function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, ?int $position = null): string|false
    {
        return '';
    }
}

if (! function_exists('get_current_screen')) {
    function get_current_screen(): ?object
    {
        return null;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
    {
        // Tests can seed stored meta in $GLOBALS['_wp_post_meta'][post_id][meta_key].
        if (isset($GLOBALS['_wp_post_meta'][$post_id][$key])) {
            $v = $GLOBALS['_wp_post_meta'][$post_id][$key];
            return $single ? $v : [$v];
        }
        return $single ? '' : [];
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $meta_key, mixed $meta_value): int|bool
    {
        return true;
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(mixed $action = ''): string
    {
        return 'test_nonce_' . md5((string) $action);
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, mixed $action = ''): int|false
    {
        return 1;
    }
}

if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field(mixed $action = '', string $name = '_wpnonce', bool $referer = true, bool $echo = true): string
    {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="test_nonce">';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)));
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg(mixed $key, mixed $value = false, string $url = ''): string
    {
        if (is_array($key)) {
            $params = $key;
            $url    = is_string($value) ? $value : '';
        } else {
            $params = [$key => $value];
        }
        $base = $url ?: 'https://example.com/wp-admin/admin.php';
        return $base . '?' . http_build_query($params);
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        // Minimal stub: strip tags not allowed in post content (script, iframe, etc.)
        return strip_tags($data, ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'pre', 'code', 'span', 'div']);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail(mixed $to, string $subject, string $message, mixed $headers = '', mixed $attachments = []): bool
    {
        $GLOBALS['_ctrlf_wp_mail_calls'][] = [
            'to'      => $to,
            'subject' => $subject,
            'message' => $message,
        ];
        return true;
    }
}

if (! function_exists('get_post')) {
    function get_post(mixed $post = null, string $output = 'OBJECT', string $filter = 'raw'): mixed
    {
        // Tests that need missing posts set $GLOBALS['_wp_posts'] (id => post).
        if (isset($GLOBALS['_wp_posts']) && is_array($GLOBALS['_wp_posts'])) {
            $id = is_object($post) ? (int) $post->ID : (int) $post;
            return $GLOBALS['_wp_posts'][$id] ?? null;
        }
        if (is_int($post)) {
            $obj              = new \stdClass();
            $obj->ID          = $post;
            $obj->post_title  = 'Test Post ' . $post;
            $obj->post_type   = 'post';
            $obj->post_status = 'publish';
            return $obj;
        }
        return null;
    }
}

if (! function_exists('get_the_title')) {
    function get_the_title(mixed $post = null): string
    {
        return 'Test Post';
    }
}

if (! function_exists('get_post_mime_type')) {
    function get_post_mime_type(mixed $post = null): string|false
    {
        return 'application/pdf';
    }
}

if (! function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata(int $attachment_id, bool $unfiltered = false): array|false
    {
        return ['width' => 800, 'height' => 600];
    }
}

if (! function_exists('get_edit_post_link')) {
    function get_edit_post_link(mixed $id = null, string $context = 'display'): string|null
    {
        return 'https://example.com/wp-admin/post.php?post=' . (int) $id . '&action=edit';
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int    $ID         = 0;
        public string $post_title  = '';
        public string $post_status = 'publish';
        public string $post_type   = 'post';
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(mixed $post = 0): string|false
    {
        return 'https://example.test/?p=' . (is_object($post) ? $post->ID : (int) $post);
    }
}

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $id): string|false
    {
        return 'https://example.test/uploads/' . $id . '.jpg';
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('get_term')) {
    // Terms exist when listed in $GLOBALS['_wp_terms'] (id => object), else none.
    function get_term(mixed $term, string $taxonomy = ''): mixed
    {
        $id = is_object($term) ? (int) $term->term_id : (int) $term;
        return $GLOBALS['_wp_terms'][$id] ?? null;
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}
