<?php

declare(strict_types=1);

namespace CtrlField\Fields\Renderers;

use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\MessageField;

final class MessageRenderer extends AbstractRenderer
{
    public function render(FieldDefinition $field, string $statePath): string
    {
        $content = '';
        $type    = 'info';

        if ($field instanceof MessageField) {
            $content = $field->getContent();
            $type    = $field->getMessageType();
        }

        // Sanitize content — allow basic HTML tags (same as wp_kses_post)
        $safeContent = function_exists('wp_kses_post') ? wp_kses_post($content) : htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::box($type, $safeContent);
    }

    /**
     * The message box: icon, coloured by type (info | success | warning | error).
     * $safeHtml must already be escaped; $extra (escaped too) goes after the body,
     * e.g. a close button. Also used by the plugin's own admin notices.
     */
    public static function box(string $type, string $safeHtml, string $extra = ''): string
    {
        $icons = [
            'info'    => '<circle cx="10" cy="10" r="7.5"/><path d="M10 9v5M10 6.2v.1"/>',
            'success' => '<circle cx="10" cy="10" r="7.5"/><path d="M6.8 10.3l2.2 2.2 4.2-4.6"/>',
            'warning' => '<path d="M10 3.2l7.6 13.3H2.4z"/><path d="M10 8.2v3.8M10 14.2v.1"/>',
            'error'   => '<circle cx="10" cy="10" r="7.5"/><path d="M7.4 7.4l5.2 5.2M12.6 7.4l-5.2 5.2"/>',
        ];
        $type = isset($icons[$type]) ? $type : 'info';
        $icon = '<svg class="ctrlf-message__icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
            . $icons[$type] . '</svg>';
        $role = in_array($type, ['warning', 'error'], true) ? 'alert' : 'note';
        $body = preg_match('/<(p|ul|ol|div|h[1-6])\b/i', $safeHtml) ? $safeHtml : '<p>' . $safeHtml . '</p>';

        return '<div class="ctrlf-message ctrlf-message--' . $type . '" role="' . $role . '">' . $icon
            . '<div class="ctrlf-message__body">' . $body . '</div>' . $extra . '</div>';
    }

    /** A box with a close button (needs Alpine on the page), for admin notices. */
    public static function dismissible(string $type, string $safeHtml): string
    {
        $label = function_exists('esc_attr__') ? esc_attr__('Dismiss', 'ctrlfield') : 'Dismiss';
        $close = '<button type="button" class="ctrlf-message__close" @click="$root.remove()" aria-label="' . $label . '">'
            . '<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>';

        return '<div class="ctrlf-notice" x-data>' . self::box($type, $safeHtml, $close) . '</div>';
    }
}
