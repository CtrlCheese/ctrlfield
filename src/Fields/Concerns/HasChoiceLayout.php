<?php

declare(strict_types=1);

namespace CtrlField\Fields\Concerns;

/**
 * How checkbox and radio choices are laid out: one per line (default) or
 * side by side, as plain choices or as cards (a framed box per choice that
 * turns violet when chosen — good for a few short options).
 */
trait HasChoiceLayout
{
    private string $choiceLayout = 'vertical';

    private bool $cards = false;

    /** @param 'vertical'|'horizontal' $layout */
    public function layout(string $layout): static
    {
        $this->choiceLayout = $layout === 'horizontal' ? 'horizontal' : 'vertical';
        return $this;
    }

    public function getLayout(): string
    {
        return $this->choiceLayout;
    }

    public function cards(bool $cards = true): static
    {
        $this->cards = $cards;
        return $this;
    }

    public function hasCards(): bool
    {
        return $this->cards;
    }

    /** CSS classes of the choices' wrapper. */
    public function choiceClasses(string $base): string
    {
        return $base
            . ($this->choiceLayout === 'horizontal' ? ' ctrlf-choices--row' : '')
            . ($this->cards ? ' ctrlf-choices--cards' : '');
    }

    /** @return array<string, mixed> */
    protected function choiceDefinition(): array
    {
        return ['layout' => $this->choiceLayout, 'cards' => $this->cards];
    }
}
