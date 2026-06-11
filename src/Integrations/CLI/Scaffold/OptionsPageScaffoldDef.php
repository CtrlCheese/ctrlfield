<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Scaffold;

final class OptionsPageScaffoldDef
{
    /** @param array<int, FieldScaffoldDef> $fields */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $capability,
        public readonly string $parent,
        public readonly array  $fields,
        public readonly bool   $noRegister,
    ) {}
}
