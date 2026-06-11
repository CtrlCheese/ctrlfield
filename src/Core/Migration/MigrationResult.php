<?php

declare(strict_types=1);

namespace FieldForge\Core\Migration;

/**
 * Output value object from MigrationEngine::run() for a single post.
 */
final class MigrationResult
{
    /**
     * @param array<string, mixed> $data   Final field data after migration (or original data on failure).
     * @param string               $error  Non-empty only when success === false.
     */
    public function __construct(
        public readonly int    $postId,
        public readonly bool   $success,
        public readonly int    $fromVersion,
        public readonly int    $toVersion,
        public readonly array  $data,
        public readonly string $error = '',
    ) {}

    public function wasUpgraded(): bool
    {
        return $this->success && $this->toVersion > $this->fromVersion;
    }

    public function wasAlreadyCurrent(): bool
    {
        return $this->success && $this->toVersion === $this->fromVersion;
    }
}
