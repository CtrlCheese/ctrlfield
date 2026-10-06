<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class ColorField extends FieldDefinition
{
    /** @var array<string> Preset hex colors shown as swatches below the picker. */
    private array $palette     = [];
    private bool  $enableAlpha = false;

    public function getType(): FieldType
    {
        return FieldType::COLOR;
    }

    /**
     * @param array<string> $colors Hex strings, e.g. ['#FF5733', '#33FF57'].
     */
    public function palette(array $colors): static
    {
        $this->palette = $colors;
        return $this;
    }

    public function enableAlpha(bool $enable = true): static
    {
        $this->enableAlpha = $enable;
        return $this;
    }

    /** @return array<string> */
    public function getPalette(): array
    {
        return $this->palette;
    }

    public function isAlphaEnabled(): bool
    {
        return $this->enableAlpha;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'palette'      => $this->palette,
            'enable_alpha' => $this->enableAlpha,
        ]);
    }
}
