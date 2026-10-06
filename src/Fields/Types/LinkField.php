<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Compound field storing { url, title, target } as a JSON object.
 * NOT a GroupField — the 3 sub-inputs are hardwired, not user-defined.
 * target is always '_self' or '_blank'.
 */
final class LinkField extends FieldDefinition
{
    private bool $showTarget = true;

    public function getType(): FieldType
    {
        return FieldType::LINK;
    }

    /** Whether to show the "open in new tab" toggle in the admin UI. */
    public function showTarget(bool $show = true): static
    {
        $this->showTarget = $show;
        return $this;
    }

    public function getShowTarget(): bool
    {
        return $this->showTarget;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'show_target' => $this->showTarget,
        ]);
    }
}
