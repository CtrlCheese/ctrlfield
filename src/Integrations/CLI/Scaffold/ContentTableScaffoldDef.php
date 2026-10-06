<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Scaffold;

final class ContentTableScaffoldDef
{
    /** @param array<int, FieldScaffoldDef> $fields */
    public function __construct(
        public readonly string $slug,
        public readonly string $singularLabel,
        public readonly string $pluralLabel,
        public readonly bool   $timestamps,
        public readonly array  $fields,
        public readonly bool   $noRegister,
    ) {}
}
