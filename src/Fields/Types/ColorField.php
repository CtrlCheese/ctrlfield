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
    private bool  $themePalette = false;

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

    /** Swatches from the theme's colour palette (theme.json), before ->palette() colours. */
    public function themePalette(bool $use = true): static
    {
        $this->themePalette = $use;
        return $this;
    }

    /**
     * The swatches: the theme's palette (when asked for) and the field's own.
     *
     * @return array<string> hex colours
     */
    public function getPalette(): array
    {
        $colors = [];
        if ($this->themePalette && function_exists('wp_get_global_settings')) {
            $sets = wp_get_global_settings(['color', 'palette']);
            foreach (['theme', 'custom'] as $origin) {
                foreach ((array) ($sets[$origin] ?? []) as $entry) {
                    if (is_array($entry) && is_string($entry['color'] ?? null)) {
                        $colors[] = $entry['color'];
                    }
                }
            }
        }

        return array_values(array_unique([...$colors, ...$this->palette]));
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
            'theme_palette' => $this->themePalette,
        ]);
    }
}
