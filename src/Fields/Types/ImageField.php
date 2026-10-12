<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class ImageField extends FieldDefinition
{
    /** WordPress image size shown in the admin (thumbnail, medium, medium_large, large, full…). */
    private string $previewSize = 'medium';

    public function getType(): FieldType
    {
        return FieldType::IMAGE;
    }

    /** Which registered image size the admin preview uses — it also sets the preview's width. */
    public function previewSize(string $size): static
    {
        $size              = sanitize_key($size);
        $this->previewSize = $size !== '' ? $size : 'medium';
        return $this;
    }

    public function getPreviewSize(): string
    {
        return $this->previewSize;
    }

    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['preview_size' => $this->previewSize]);
    }
}
