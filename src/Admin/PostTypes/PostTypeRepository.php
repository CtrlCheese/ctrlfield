<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * Stores admin-created post types in a single option.
 */
final class PostTypeRepository
{
    public const OPTION_KEY = 'ctrlfield_ui_post_types';

    /** Set when definitions change; rewrite rules are flushed once on the next init. */
    public const FLUSH_FLAG = 'ctrlfield_ui_post_types_flush';

    /** @return array<string, PostTypeDefinition> keyed by slug */
    public function all(): array
    {
        $rows = get_option(self::OPTION_KEY, []);
        $defs = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ! empty($row['slug'])) {
                $def              = PostTypeDefinition::fromArray($row);
                $defs[$def->slug] = $def;
            }
        }

        ksort($defs);

        return $defs;
    }

    public function get(string $slug): ?PostTypeDefinition
    {
        return $this->all()[$slug] ?? null;
    }

    public function save(PostTypeDefinition $def, ?string $previousSlug = null): void
    {
        $defs = $this->all();

        if ($previousSlug !== null && $previousSlug !== $def->slug) {
            unset($defs[$previousSlug]);
        }

        $defs[$def->slug] = $def;
        $this->persist($defs);
    }

    public function delete(string $slug): void
    {
        $defs = $this->all();
        unset($defs[$slug]);
        $this->persist($defs);
    }

    /** @param array<string, PostTypeDefinition> $defs */
    private function persist(array $defs): void
    {
        update_option(
            self::OPTION_KEY,
            array_values(array_map(static fn (PostTypeDefinition $d) => $d->toArray(), $defs)),
            true // autoloaded: read on every init
        );
        update_option(self::FLUSH_FLAG, 1, true);
    }
}
