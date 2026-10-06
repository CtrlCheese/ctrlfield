<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

/**
 * Implemented by field types whose data lives outside the JSON blob
 * (e.g., RelationshipField stores in a dedicated pivot table).
 *
 * PersistenceStage checks for this interface and:
 * 1. Excludes the field from the JSON blob write.
 * 2. Calls persistExternal() so the field handles its own persistence.
 */
interface ExternalStorageInterface
{
    public function persistExternal(int $postId, mixed $value): void;
}
