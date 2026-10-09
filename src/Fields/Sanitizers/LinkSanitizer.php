<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

use CtrlField\Fields\Contracts\SanitizerInterface;

final class LinkSanitizer implements SanitizerInterface
{
    /** Link kinds the dialog produces. */
    public const TYPES = ['post', 'term', 'anchor', 'external'];

    /** @param list<string> $styles Allowed button styles; empty = no style is stored. */
    public function __construct(private readonly array $styles = []) {}

    /**
     * Returns a sanitized ['url', 'title', 'target'] array, plus type / id /
     * object / style when the link has them (older links stay unchanged).
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
        }

        $style = (string) ($value['style'] ?? '');
        if ($style !== '' && in_array($style, $this->styles, true)) {
            $link['style'] = $style;
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
