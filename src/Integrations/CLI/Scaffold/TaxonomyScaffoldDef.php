<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Scaffold;

final class TaxonomyScaffoldDef
{
    /** @param array<int, string> $attachTo  @param array<int, FieldScaffoldDef> $fields */
    public function __construct(
        public readonly string $slug,
        public readonly string $singularLabel,
        public readonly string $pluralLabel,
        public readonly array  $attachTo,
        public readonly bool   $hierarchical,
        public readonly array  $fields,
        public readonly bool   $noRegister,
    ) {}
}
