<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * Keys a post type or taxonomy must not use: WordPress's own types and the
 * query variables it reserves (a post type or taxonomy key becomes a query
 * var, so "year" or "name" would break front-end queries).
 */
final class ReservedNames
{
    /** Post types WordPress core uses. */
    public const POST_TYPES = [
        'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css',
        'customize_changeset', 'oembed_cache', 'user_request', 'wp_block',
        'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
        'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme',
    ];

    /** Taxonomies WordPress core uses. */
    public const TAXONOMIES = [
        'category', 'post_tag', 'nav_menu', 'link_category', 'post_format',
        'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
    ];

    /** Reserved terms (developer.wordpress.org: register_taxonomy, "Reserved Terms"). */
    public const TERMS = [
        'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat',
        'category', 'category__and', 'category__in', 'category__not_in', 'category_name',
        'comments_per_page', 'comments_popup', 'custom', 'customize_messenger_channel',
        'customized', 'cpage', 'day', 'debug', 'embed', 'error', 'exact', 'feed', 'fields',
        'hour', 'link_category', 'm', 'minute', 'monthnum', 'more', 'name', 'nav_menu',
        'nonce', 'nopaging', 'offset', 'order', 'orderby', 'p', 'page', 'page_id', 'paged',
        'pagename', 'pb', 'perm', 'post', 'post__in', 'post__not_in', 'post_format',
        'post_mime_type', 'post_status', 'post_tag', 'post_type', 'posts', 'posts_per_archive_page',
        'posts_per_page', 'preview', 'robots', 's', 'search', 'second', 'sentence', 'showposts',
        'static', 'status', 'subpost', 'subpost_id', 'tag', 'tag__and', 'tag__in', 'tag__not_in',
        'tag_id', 'tag_slug__and', 'tag_slug__in', 'taxonomy', 'tb', 'term', 'terms', 'theme',
        'title', 'type', 'types', 'w', 'withcomments', 'withoutcomments', 'year',
    ];

    public static function forPostType(string $key): bool
    {
        return in_array($key, self::POST_TYPES, true) || in_array($key, self::TERMS, true);
    }

    public static function forTaxonomy(string $key): bool
    {
        return in_array($key, self::TAXONOMIES, true) || in_array($key, self::TERMS, true);
    }
}
