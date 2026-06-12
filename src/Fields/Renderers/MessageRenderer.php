<?php

declare(strict_types=1);

namespace FieldForge\Fields\Renderers;

use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\Types\MessageField;

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
        $safeType    = $this->esc($type);

        return sprintf(
            '<div class="ff-message ff-message--%s"><p>%s</p></div>',
            $safeType,
            $safeContent,
        );
    }
}
