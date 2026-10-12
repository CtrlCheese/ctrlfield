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
        // A multiple link field: a list of links.
        if (is_array($link) && $link !== [] && array_is_list($link)) {
            return array_map(self::resolve(...), $link);
        }
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

    /**
     * A link as a button, ready to print: <a> with the link's style as a class
     * (btn btn--primary), its icon on the chosen side and its text. Empty
     * string when the link has no URL. A list of links prints one after the other.
     *
     *   <?= ctrlfield_button($cta) ?>
     *   <?= ctrlfield_button($links, ['class' => 'button', 'prefix' => 'button--']) ?>
     *
     * @param array{class?: string, prefix?: string, icon?: array<string, scalar>} $options
     *        class: base class (default "btn"); prefix: style class prefix (default "btn--");
     *        icon: attributes of the icon (ctrlfield_icon()).
     */
    public static function button(mixed $link, array $options = []): string
    {
        if (is_array($link) && $link !== [] && array_is_list($link)) {
            return implode('', array_map(static fn ($l) => self::button($l, $options), $link));
        }
        $link  = self::resolve($link);
        $attrs = self::attributes($link);
        if ($attrs === '' || ! is_array($link)) {
            return '';
        }

        $base    = (string) ($options['class'] ?? 'btn');
        $prefix  = (string) ($options['prefix'] ?? 'btn--');
        $style   = (string) ($link['style'] ?? '');
        $class   = trim($base . ($style !== '' ? ' ' . $prefix . $style : ''));
        $icon    = isset($link['icon']) ? IconLibrary::html($link['icon'], ($options['icon'] ?? []) + ['width' => 18, 'height' => 18]) : '';
        $text    = '<span>' . esc_html((string) ($link['title'] ?? '')) . '</span>';
        $content = $icon === '' ? $text : (($link['icon_position'] ?? 'left') === 'right' ? $text . $icon : $icon . $text);

        return '<a ' . $attrs . ($class !== '' ? ' class="' . esc_attr($class) . '"' : '') . '>' . $content . '</a>';
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
