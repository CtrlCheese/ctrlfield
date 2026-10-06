<?php

declare(strict_types=1);

namespace CtrlField\Registry;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Exceptions\UnresolvedCloneException;

/**
 * Holds FieldGroups whose CloneField sources are not yet registered.
 *
 * When a source group is registered (via FieldGroup::register()),
 * FieldGroup calls PendingCloneRegistry::resolve($key) which re-triggers
 * registration of every group that was waiting on that key.
 *
 * BootManager checks assertNoPending() after all schema files have loaded.
 */
final class PendingCloneRegistry
{
    /** @var array<string, array<int, FieldGroup>> sourceGroupKey → waiting groups */
    private static array $pending = [];

    /**
     * Defer a FieldGroup until its clone source becomes available.
     */
    public static function defer(string $sourceGroupKey, FieldGroup $group): void
    {
        self::$pending[$sourceGroupKey][] = $group;
    }

    /**
     * Called whenever a FieldGroup is successfully registered.
     * Triggers re-registration of any groups waiting on $resolvedGroupKey.
     */
    public static function resolve(string $resolvedGroupKey): void
    {
        if (! isset(self::$pending[$resolvedGroupKey])) {
            return;
        }

        $waiting = self::$pending[$resolvedGroupKey];
        unset(self::$pending[$resolvedGroupKey]);

        foreach ($waiting as $group) {
            $group->register();
        }
    }

    /**
     * Returns true if any groups are still waiting for a source.
     */
    public static function hasPending(): bool
    {
        return ! empty(self::$pending);
    }

    /**
     * @return array<string, array<int, FieldGroup>>
     */
    public static function getPending(): array
    {
        return self::$pending;
    }

    /**
     * Throws UnresolvedCloneException if any groups are still pending.
     * Called after all schema files have been loaded.
     *
     * @throws UnresolvedCloneException
     */
    public static function assertNoPending(): void
    {
        if (empty(self::$pending)) {
            return;
        }

        $details = [];
        foreach (self::$pending as $sourceKey => $groups) {
            foreach ($groups as $group) {
                $details[] = "Group '{$group->getKey()}' waiting for source '{$sourceKey}'";
            }
        }

        throw new UnresolvedCloneException(
            'Unresolved CloneField sources after boot: ' . implode('; ', $details) . '. '
            . 'Ensure the library group is registered before or in the same schema file as its consumer.'
        );
    }

    public static function reset(): void
    {
        self::$pending = [];
    }
}
