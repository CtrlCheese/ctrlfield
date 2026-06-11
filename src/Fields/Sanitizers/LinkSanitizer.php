<?php

declare(strict_types=1);

namespace FieldForge\Fields\Sanitizers;

use FieldForge\Fields\Contracts\SanitizerInterface;

final class LinkSanitizer implements SanitizerInterface
{
    /**
     * Returns a sanitized ['url', 'title', 'target'] array.
     * Returns null when the input is not an array or when url is missing.
     *
     * @return array{url: string, title: string, target: string}|null
     */
    public function sanitize(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $url = isset($value['url']) ? $this->sanitizeUrl((string) $value['url']) : '';

        return [
            'url'    => $url,
            'title'  => isset($value['title']) ? $this->sanitizeText((string) $value['title']) : '',
            'target' => (($value['target'] ?? '') === '_blank') ? '_blank' : '_self',
        ];
    }

    private function sanitizeUrl(string $value): string
    {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($value);
        }
        $filtered = filter_var(trim($value), FILTER_SANITIZE_URL);
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
