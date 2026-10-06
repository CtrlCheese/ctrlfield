<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Scaffold;

final class FieldGroupScaffoldDef
{
    /**
     * @param array<int, FieldScaffoldDef> $fields
     * Where conditions — one of: postType, taxonomy, optionsPage, context (user_profile|comment).
     */
    public function __construct(
        public readonly string  $slug,
        public readonly string  $title,
        public readonly ?string $postType,
        public readonly ?string $taxonomy,
        public readonly ?string $optionsPage,
        public readonly ?string $context,
        public readonly array   $fields,
        public readonly bool    $noRegister,
    ) {}
}
