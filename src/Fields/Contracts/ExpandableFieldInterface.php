<?php

declare(strict_types=1);

namespace CtrlField\Fields\Contracts;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\FieldDefinition;

/**
 * Implemented by CloneField (Pro).
 *
 * FieldGroup::register() detects this interface and calls expand() to
 * replace the meta-field with concrete, prefixed FieldDefinition instances.
 * If the source group is not yet in FieldRegistry, registration is deferred
 * via PendingCloneRegistry until the source becomes available.
 */
interface ExpandableFieldInterface
{
    /**
     * Returns the FieldGroup key this clone depends on.
     * Used by PendingCloneRegistry for deferred resolution.
     */
    public function getSourceGroupKey(): string;

    /**
     * Expand into concrete FieldDefinition instances with prefixed keys.
     *
     * @param  FieldGroup $sourceGroup the resolved source group
     * @return array<int, FieldDefinition>
     * @throws \CtrlField\Fields\Exceptions\NonLibraryGroupCloneException if source group has conditions
     * @throws \CtrlField\Fields\Exceptions\InvalidCloneNestingException  if source group contains a clone
     */
    public function expand(FieldGroup $sourceGroup): array;
}
