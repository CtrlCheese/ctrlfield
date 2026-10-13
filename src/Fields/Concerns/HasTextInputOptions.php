<?php

declare(strict_types=1);

namespace CtrlField\Fields\Concerns;

/**
 * Options of the single-value text inputs (text, textarea, email, URL,
 * password, number): a fixed text before / after the input (R$, %, px) and
 * a maximum length, with a counter in the editor and checked on save.
 */
trait HasTextInputOptions
{
    private string $prepend = '';

    private string $append = '';

    private ?int $maxLength = null;

    /** Fixed text shown before the input, e.g. "R$" or "https://". Not stored. */
    public function prepend(string $text): static
    {
        $this->prepend = $text;
        return $this;
    }

    /** Fixed text shown after the input, e.g. "%" or "px". Not stored. */
    public function append(string $text): static
    {
        $this->append = $text;
        return $this;
    }

    /** At most this many characters: a counter in the editor, refused on save. */
    public function maxLength(int $chars): static
    {
        $this->maxLength = $chars > 0 ? $chars : null;
        return $this;
    }

    public function getPrepend(): string
    {
        return $this->prepend;
    }

    public function getAppend(): string
    {
        return $this->append;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /** @return array<string, mixed> */
    protected function textInputDefinition(): array
    {
        return ['prepend' => $this->prepend, 'append' => $this->append, 'max_length' => $this->maxLength];
    }
}
