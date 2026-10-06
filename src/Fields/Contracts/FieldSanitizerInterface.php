<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

/**
 * Implemented by field types that require custom sanitization logic
 * (e.g., PostObjectField validates IDs against the database).
 *
 * SanitizationStage calls sanitizeForStorage() before the type-based match,
 * allowing Pro fields to inject sanitization without Core knowing their specifics.
 */
interface FieldSanitizerInterface
{
    public function sanitizeForStorage(mixed $value): mixed;
}
