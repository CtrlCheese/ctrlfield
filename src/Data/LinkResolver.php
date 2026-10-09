<?php

declare(strict_types=1);

namespace CtrlField\Data;

/**
 * Brings a stored link up to date when it is read: a link to a post or term
 * keeps its id, so its URL follows permalink changes. When the post or term no
 * longer exists (or is not published), the stored URL is kept.
 */
final class LinkResolver
{
    /**
     * @param  mixed $link A stored link value.
     * @return mixed       The same value, with an up-to-date url when it can be resolved.
     */
    public static function resolve(mixed $link): mixed
    {
        if (! is_array($link) || empty($link['id'])) {
            return $link;
        }

        $url = match ($link['type'] ?? '') {
            'post'  => self::postUrl((int) $link['id']),
            'term'  => self::termUrl((int) $link['id'], (string) ($link['object'] ?? '')),
            default => null,
        };

        if ($url !== null && $url !== '') {
            // A fragment typed after the address ("…/contact/#form") is kept.
            $fragment = (string) (parse_url((string) ($link['url'] ?? ''), PHP_URL_FRAGMENT) ?? '');
            $link['url'] = $fragment !== '' ? $url . '#' . $fragment : $url;
        }

        return $link;
    }

    /**
     * href / target / rel for an <a>, ready to print (escaped).
     *
     * @param mixed $link A link value (resolved or not).
     */
    public static function attributes(mixed $link): string
    {
        $link = self::resolve($link);
        if (! is_array($link) || ($link['url'] ?? '') === '') {
            return '';
        }

        $attrs = ' href="' . esc_url((string) $link['url']) . '"';
        if (($link['target'] ?? '') === '_blank') {
            $attrs .= ' target="_blank" rel="noopener noreferrer"';
        }

        return ltrim($attrs);
    }

    private static function postUrl(int $id): ?string
    {
        if (! function_exists('get_post_status') || get_post_status($id) !== 'publish') {
            return null;
        }
        $url = get_permalink($id);

        return is_string($url) ? $url : null;
    }

    private static function termUrl(int $id, string $taxonomy): ?string
    {
        if (! function_exists('get_term_link')) {
            return null;
        }
        $url = get_term_link($id, $taxonomy);

        return is_string($url) ? $url : null;
    }
}
