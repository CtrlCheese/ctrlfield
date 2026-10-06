<?php

declare(strict_types=1);

namespace CtrlField\Core\Pipeline;

use CtrlField\Builder\FieldGroup;

/**
 * Mutable carrier of state through all pipeline stages.
 *
 * Immutable inputs (postId, rawPost, fieldGroups) are set at construction.
 * Stages mutate fields and indexedFields as data is decoded, coerced, and sanitized.
 */
class PipelineContext
{
    /** @var array<string, mixed> Decoded and progressively refined field values. */
    public array $fields = [];

    /** @var array<string, mixed> Fields with setIndex(true), written as individual meta rows. */
    public array $indexedFields = [];

    /**
     * @param array<string, mixed> $rawPost     Raw $_POST data.
     * @param FieldGroup[]         $fieldGroups Pre-resolved groups matching this post's context.
     */
    public function __construct(
        public readonly int   $postId,
        public readonly array $rawPost,
        public readonly array $fieldGroups = [],
    ) {}
}
