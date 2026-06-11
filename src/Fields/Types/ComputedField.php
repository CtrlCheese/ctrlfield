<?php

declare(strict_types=1);

namespace FieldForge\Fields\Types;

use FieldForge\Enums\FieldType;
use FieldForge\Fields\FieldDefinition;

/**
 * A field whose value is computed server-side from other fields in the same group.
 *
 * Computed fields:
 * - Are not rendered as inputs in the meta box (unless ->showInAdmin() is set)
 * - Are not included in the Alpine fieldforge_payload (never sent from the browser)
 * - Are stored in _fieldforge_data like any other field
 * - Are calculated by ComputedFieldsStage after Sanitization, before Persistence
 *
 * Usage:
 *   Field::computed('full_name', static fn($f) => trim($f['first'] . ' ' . $f['last']))
 */
final class ComputedField extends FieldDefinition
{
    private bool $showInAdmin = false;

    public function __construct(
        string $key,
        private readonly \Closure $callback,
    ) {
        parent::__construct($key);
    }

    public function getType(): FieldType
    {
        return FieldType::COMPUTED;
    }

    /**
     * Computes the field value from the current field data array.
     *
     * @param array<string, mixed> $fields  All field values after sanitization.
     */
    public function compute(array $fields): mixed
    {
        return ($this->callback)($fields);
    }

    /**
     * When set, renders a read-only <p> in the meta box.
     * Without this, the field is invisible to users (but still stored).
     */
    public function showInAdmin(bool $show = true): static
    {
        $this->showInAdmin = $show;
        return $this;
    }

    public function isShowInAdmin(): bool
    {
        return $this->showInAdmin;
    }
}
