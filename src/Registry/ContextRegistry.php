<?php

declare(strict_types=1);

namespace CtrlField\Registry;

use CtrlField\Builder\AdminContext;
use CtrlField\Builder\FieldGroup;
use CtrlField\Registry\Contracts\ContextInterface;
use CtrlField\Registry\Exceptions\DuplicateContextKeyException;
use CtrlField\Registry\Resolvers\ContextTypeResolver;
use CtrlField\Registry\Resolvers\OptionsPageResolver;
use CtrlField\Registry\Resolvers\PostTypeResolver;
use CtrlField\Registry\Resolvers\TaxonomyResolver;

class ContextRegistry
{
    /**
     * Built-in context key resolvers — always available, cannot be overridden.
     *
     * @var array<string, class-string<ContextInterface>>
     */
    private static array $builtIn = [
        'post_type'    => PostTypeResolver::class,
        'options_page' => OptionsPageResolver::class,
        'taxonomy'     => TaxonomyResolver::class,
        'context'      => ContextTypeResolver::class,
    ];

    /**
     * Custom context key resolvers registered by third parties.
     *
     * @var array<string, class-string<ContextInterface>>
     */
    private static array $custom = [];

    /**
     * Register a custom context resolver for a third-party key.
     * Built-in keys (post_type, options_page, taxonomy, context) cannot be re-registered.
     *
     * @param class-string<ContextInterface> $resolverClass
     * @throws DuplicateContextKeyException
     */
    public static function register(string $contextKey, string $resolverClass): void
    {
        if (isset(self::$builtIn[$contextKey]) || isset(self::$custom[$contextKey])) {
            throw new DuplicateContextKeyException(
                "Context key '{$contextKey}' is already registered and cannot be re-registered."
            );
        }

        self::$custom[$contextKey] = $resolverClass;
    }

    /**
     * Returns true if the given key has a resolver (built-in or custom).
     */
    public static function isValidKey(string $key): bool
    {
        return isset(self::$builtIn[$key]) || isset(self::$custom[$key]);
    }

    /**
     * Returns all registered field groups that match the given admin context.
     *
     * @return array<string, FieldGroup>
     */
    public static function resolve(AdminContext $context): array
    {
        return array_filter(
            FieldRegistry::all(),
            static fn(FieldGroup $group): bool =>
                self::groupMatches($group, $context)
                && self::capabilityAllows($group),
        );
    }

    private static function capabilityAllows(FieldGroup $group): bool
    {
        $cap = $group->getRequiredCapability();

        if ($cap === '') {
            return true;
        }

        return function_exists('current_user_can') && current_user_can($cap);
    }

    /**
     * Evaluates whether a FieldGroup matches the given AdminContext.
     * Called by FieldGroup::matches() for backwards compatibility.
     */
    public static function groupMatches(FieldGroup $group, AdminContext $context): bool
    {
        $andConditions = $group->getAndConditions();
        $orGroups      = $group->getOrGroups();

        if (empty($andConditions) && empty($orGroups)) {
            return false;
        }

        foreach ($andConditions as $condition) {
            if (! self::evaluateOne($condition, $context)) {
                return false;
            }
        }

        foreach ($orGroups as $orGroup) {
            $anyMatch = false;
            foreach ($orGroup as $condition) {
                if (self::evaluateOne($condition, $context)) {
                    $anyMatch = true;
                    break;
                }
            }
            if (! $anyMatch) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clears only custom registrations — built-in resolvers are never removed.
     * Used in tests to isolate custom resolver registrations.
     */
    public static function reset(): void
    {
        self::$custom = [];
    }

    /** @param array{key: string, operator: string, value: mixed} $condition */
    private static function evaluateOne(array $condition, AdminContext $context): bool
    {
        $all   = array_merge(self::$builtIn, self::$custom);
        $class = $all[$condition['key']] ?? null;

        if ($class === null) {
            return false;
        }

        return $class::matches($context, $condition['operator'], $condition['value']);
    }
}
