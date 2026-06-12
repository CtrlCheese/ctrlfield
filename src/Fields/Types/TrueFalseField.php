<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

final class TrueFalseField extends FieldDefinition
{
    private string $trueLabel  = 'Yes';
    private string $falseLabel = 'No';
    private string $message    = '';

    public function getType(): FieldType
    {
        return FieldType::TRUE_FALSE;
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;
        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;
        return $this;
    }

    /**
     * Optional descriptive text rendered next to the toggle — e.g. "Enable maintenance mode".
     */
    public function message(string $text): static
    {
        $this->message = $text;
        return $this;
    }

    public function getTrueLabel(): string  { return $this->trueLabel; }
    public function getFalseLabel(): string { return $this->falseLabel; }
    public function getMessage(): string    { return $this->message; }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'true_label'  => $this->trueLabel,
            'false_label' => $this->falseLabel,
            'message'     => $this->message,
        ]);
    }
}
