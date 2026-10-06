<?php

declare(strict_types=1);

namespace CtrlField\Storage;

use CtrlField\Builder\FieldGroup;
use CtrlField\Storage\Contracts\StorageAdapterInterface;
use CtrlField\Storage\Drivers\WpOptionsDriver;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\Exceptions\UnresolvableAdapterException;

/**
 * Determines which StorageAdapter a FieldGroup should use,
 * based on the context keys in its where() conditions.
 */
final class StorageAdapterResolver
{
    private const KEY_TO_BACKEND = [
        'post_type'    => 'post',
        'taxonomy'     => 'term',
        'options_page' => 'options',
    ];

    // context key values that map to specific backends
    private const CONTEXT_VALUE_TO_BACKEND = [
        'user_profile' => 'user',
        'comment'      => 'comment',
    ];

    public static function resolve(FieldGroup $group): StorageAdapterInterface
    {
        $backends = self::detectBackends($group);

        if (count($backends) === 0) {
            // No conditions — default to post meta
            return new PostMetaAdapter(new WpPostMetaDriver());
        }

        if (count($backends) > 1) {
            throw new UnresolvableAdapterException(sprintf(
                "Field group '%s' spans multiple storage backends (%s). "
                . "A single group cannot target both post meta and user meta, for example.",
                $group->getKey(),
                implode(', ', array_unique($backends)),
            ));
        }

        return match (reset($backends)) {
            'post'    => new PostMetaAdapter(new WpPostMetaDriver()),
            'options' => new OptionsAdapter(new WpOptionsDriver()),
            'term'    => new TermMetaAdapter(),
            'user'    => new UserMetaAdapter(),
            'comment' => new CommentMetaAdapter(),
            default   => new PostMetaAdapter(new WpPostMetaDriver()),
        };
    }

    /** @return array<string> */
    private static function detectBackends(FieldGroup $group): array
    {
        $backends = [];

        $allConditions = array_merge(
            $group->getAndConditions(),
            ...array_map(fn($g) => $g, $group->getOrGroups()),
        );

        foreach ($allConditions as $condition) {
            $key = $condition['key'];

            if (isset(self::KEY_TO_BACKEND[$key])) {
                $backends[] = self::KEY_TO_BACKEND[$key];
                continue;
            }

            if ($key === 'context') {
                $value = $condition['value'] ?? '';
                if (isset(self::CONTEXT_VALUE_TO_BACKEND[$value])) {
                    $backends[] = self::CONTEXT_VALUE_TO_BACKEND[$value];
                }
            }
        }

        return $backends;
    }
}
