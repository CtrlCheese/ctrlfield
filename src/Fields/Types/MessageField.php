<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

final class MessageField extends FieldDefinition
{
    private string $content = '';
    private string $type    = 'info'; // 'info'|'warning'|'error'|'success'

    public function getType(): FieldType
    {
        return FieldType::MESSAGE;
    }

    public function isUiOnly(): bool
    {
        return true;
    }

    public function content(string $html): static
    {
        $this->content = $html;
        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMessageType(): string
    {
        return $this->type;
    }
}
