<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Rich text (TinyMCE). Uses the theme's editor setup — formats, plugins, the
 * mce_buttons* toolbars — like the WordPress editor (WysiwygEditorSettings).
 */
final class WysiwygField extends FieldDefinition
{
    private string $toolbar = 'full';

    private bool $mediaButtons = true;

    public function getType(): FieldType
    {
        return FieldType::WYSIWYG;
    }

    /**
     * Named toolbar: 'full' (the theme's editor toolbar), 'basic', or one added
     * with the ctrlfield/wysiwyg/toolbars (or ACF's acf/fields/wysiwyg/toolbars) filter.
     */
    public function toolbar(string $name): static
    {
        $name          = strtolower((string) preg_replace('/[^A-Za-z0-9_-]/', '', $name));
        $this->toolbar = $name !== '' ? $name : 'full';
        return $this;
    }

    public function getToolbar(): string
    {
        return $this->toolbar;
    }

    /** Show the "Add Media" button. */
    public function mediaButtons(bool $show = true): static
    {
        $this->mediaButtons = $show;
        return $this;
    }

    public function getMediaButtons(): bool
    {
        return $this->mediaButtons;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'toolbar'       => $this->toolbar,
            'media_buttons' => $this->mediaButtons,
        ]);
    }
}
