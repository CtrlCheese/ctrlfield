<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Data\IconLibrary;
use CtrlField\Fields\Contracts\SanitizerInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\LinkField;

final class LinkSanitizer implements SanitizerInterface
{
    /** Link kinds the dialog produces ('internal': a path on this site that is no post). */
    public const TYPES = ['post', 'term', 'anchor', 'internal', 'external'];

    /**
     * @param list<string> $styles   Allowed button styles; empty = no style is stored.
     * @param bool         $icons    Whether icon / icon_position are kept.
     * @param int|null     $multiple Most links of a multiple field; null = a single link.
     */
    public function __construct(
        private readonly array $styles = [],
        private readonly bool $icons = false,
        private readonly ?int $multiple = null,
        private readonly string $iconPosition = 'left',
    ) {}

    public static function forField(?FieldDefinition $field): self
    {
        if (! $field instanceof LinkField) {
            return new self();
        }

        return new self(
            array_keys($field->getStyles()),
            $field->hasIcons(),
            $field->isMultiple() ? $field->getMax() : null,
            $field->getDefaultIconPosition(),
        );
    }

    /**
     * The field's value: one link, or for a multiple field the list of links
     * with a URL, at most ->max() of them.
     *
     * @return array<string, string|int>|list<array<string, string|int>>|null
     */
    public function sanitizeValue(mixed $value): ?array
    {
        if ($this->multiple === null) {
            return $this->sanitize($value);
        }
        if (! is_array($value)) {
            return [];
        }
        $links = [];
        foreach (array_is_list($value) ? $value : [$value] as $item) {
            $link = $this->sanitize($item);
            if ($link !== null && $link['url'] !== '') {
                $links[] = $link;
            }
        }

        return array_slice($links, 0, $this->multiple);
    }

    /**
     * Returns a sanitized ['url', 'title', 'target'] array, plus type / id /
     * object / style / icon when the link has them (older links stay unchanged).
     * Returns null when the input is not an array.
     *
     * @return array<string, string|int>|null
     */
    public function sanitize(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $url = isset($value['url']) ? $this->sanitizeUrl((string) $value['url']) : '';

        $link = [
            'url'    => $url,
            'title'  => isset($value['title']) ? $this->sanitizeText((string) $value['title']) : '',
            'target' => (($value['target'] ?? '') === '_blank') ? '_blank' : '_self',
        ];

        $type = (string) ($value['type'] ?? '');
        if (in_array($type, self::TYPES, true)) {
            $link['type'] = $type;
        }

        $id = (int) ($value['id'] ?? 0);
        if ($id > 0 && in_array($type, ['post', 'term'], true)) {
            $link['id'] = $id;
            $object = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($value['object'] ?? '')));
            if ($object !== '' && $object !== null) {
                $link['object'] = $object;
            }
        } elseif ($url !== '' && in_array($type, ['internal', 'external'], true)) {
            // Typed in the dialog (older links without a type stay as they are).
            $link = self::matchSiteUrl($link);
        }

        $style = (string) ($value['style'] ?? '');
        if ($style !== '' && in_array($style, $this->styles, true)) {
            $link['style'] = $style;
        }

        if ($this->icons && IconLibrary::isValid($value['icon'] ?? null)) {
            $link['icon']          = trim((string) $value['icon']);
            $side                  = (string) ($value['icon_position'] ?? '');
            $link['icon_position'] = in_array($side, ['left', 'right'], true) ? $side : $this->iconPosition;
        }

        return $link;
    }

    /**
     * A URL typed by hand that points into this site ("/services/",
     * "https://this-site/services/#price") becomes a link to that post, so it
     * follows permalink changes like a picked one. Other paths on the site are
     * 'internal'; everything else is 'external'.
     *
     * @param  array<string, string|int> $link
     * @return array<string, string|int>
     */
    private static function matchSiteUrl(array $link): array
    {
        $url = (string) $link['url'];
        if (str_starts_with($url, '#')) {
            $link['type'] = 'anchor';
            return $link;
        }
        if (! function_exists('home_url')) {
            return $link;
        }

        $home     = (string) home_url('/');
        $homeHost = (string) parse_url($home, PHP_URL_HOST);
        $host     = parse_url($url, PHP_URL_HOST);
        $relative = str_starts_with($url, '/') && ! str_starts_with($url, '//');

        if (! $relative && (! is_string($host) || strcasecmp($host, $homeHost) !== 0)) {
            $link['type'] = 'external';
            return $link;
        }

        $absolute = $relative ? rtrim($home, '/') . $url : $url;
        $postId   = function_exists('url_to_postid') ? url_to_postid($absolute) : 0;
        if ($postId > 0) {
            $link['type']   = 'post';
            $link['id']     = $postId;
            $link['object'] = (string) get_post_type($postId);
        } else {
            $link['type'] = 'internal';
        }

        return $link;
    }

    private function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        // "#section" (an anchor on the page) is kept as typed.
        if (preg_match('/^#[A-Za-z][A-Za-z0-9_:.-]*$/', $value)) {
            return $value;
        }
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($value);
        }
        $filtered = filter_var($value, FILTER_SANITIZE_URL);
        return is_string($filtered) ? $filtered : '';
    }

    private function sanitizeText(string $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value);
        }
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
