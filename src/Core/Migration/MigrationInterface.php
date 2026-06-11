<?php

declare(strict_types=1);

namespace FieldForge\Core\Migration;

interface MigrationInterface
{
    /**
     * Transform field data from the previous version to this version.
     *
     * Must be pure: same input always produces same output, no side-effects.
     * Throw any exception to signal failure — the engine will roll back.
     *
     * @param  array<string, mixed> $data  Field data at the previous version.
     * @return array<string, mixed>        Transformed field data at this version.
     */
    public function up(array $data): array;

    /**
     * The schema version this migration produces.
     * Must be unique across all registered migrations.
     */
    public function version(): int;
}
