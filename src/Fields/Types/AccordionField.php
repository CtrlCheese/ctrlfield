<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Starts a collapsible section that runs until the next accordion or an
 * accordion end. Consecutive accordions form a set; the first one's style
 * applies to all of them:
 *  - 'stacked' (default): cards one under the other, each opens on its own;
 *  - 'horizontal': panels side by side, the open one widens, the others
 *    shrink to a narrow strip with a vertical title (one open at a time);
 *  - 'side': titles listed on the left, the chosen section on the right.
 */
final class AccordionField extends FieldDefinition
{
    public const STYLES = ['stacked', 'horizontal', 'side'];

    private bool $open = true;

    private string $style = 'stacked';

    public function getType(): FieldType
    {
        return FieldType::ACCORDION;
    }

    public function isUiOnly(): bool
    {
        return true;
    }

    public function open(bool $open = true): static
    {
        $this->open = $open;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /** @param string $style 'stacked', 'horizontal' or 'side'; anything else means 'stacked'. */
    public function style(string $style): static
    {
        $this->style = in_array($style, self::STYLES, true) ? $style : 'stacked';
        return $this;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), ['open' => $this->open, 'style' => $this->style]);
    }
}
