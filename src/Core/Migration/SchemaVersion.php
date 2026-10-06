<?php

declare(strict_types=1);

namespace CtrlField\Core\Migration;

/**
 * Single source of truth for the current schema version.
 *
 * Increment CURRENT when a new MigrationInterface implementation is shipped.
 * PostMetaAdapter::load() rejects payloads with a lower stored version.
 * PersistenceStage writes this version into every saved JSON payload.
 */
final class SchemaVersion
{
    public const CURRENT = 1;
}
