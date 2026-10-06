<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Scaffold;

final class CptScaffoldDef
{
    /** @param array<int, FieldScaffoldDef> $fields */
    public function __construct(
        public readonly string $slug,
        public readonly string $singularLabel,
        public readonly string $pluralLabel,
        public readonly string $icon,
        /** @var array<int, string> */
        public readonly array  $supports,
        public readonly array  $fields,
        public readonly bool   $noRegister,
    ) {}
}
