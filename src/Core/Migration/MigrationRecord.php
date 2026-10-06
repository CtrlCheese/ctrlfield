<?php

declare(strict_types=1);

namespace CtrlField\Core\Migration;

/**
 * Input value object for MigrationEngine::run().
 *
 * Built by the CLI command from PostMetaAdapter::loadRaw() so the engine
 * has no dependency on WP_Query or any WP runtime state.
 */
final class MigrationRecord
{
    /**
     * @param array<string, mixed> $data The field data at storedVersion.
     */
    public function __construct(
        public readonly int   $postId,
        public readonly int   $storedVersion,
        public readonly array $data,
    ) {}
}
