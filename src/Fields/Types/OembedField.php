<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Stores the raw URL string — NOT the embed HTML.
 * Embed HTML is resolved by the theme via wp_oembed_get($url).
 * The admin renders a live preview via AJAX on URL blur.
 */
final class OembedField extends FieldDefinition
{
    private int $previewWidth  = 640;
    private int $previewHeight = 360;

    public function getType(): FieldType
    {
        return FieldType::OEMBED;
    }

    public function previewWidth(int $pixels): static
    {
        $this->previewWidth = $pixels;
        return $this;
    }

    public function previewHeight(int $pixels): static
    {
        $this->previewHeight = $pixels;
        return $this;
    }

    public function getPreviewWidth(): int
    {
        return $this->previewWidth;
    }

    public function getPreviewHeight(): int
    {
        return $this->previewHeight;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'preview_width'  => $this->previewWidth,
            'preview_height' => $this->previewHeight,
        ]);
    }
}
