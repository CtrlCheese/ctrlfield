<?php

declare(strict_types=1);

namespace CtrlField\Components;

/**
 * Value object representing a discovered component.
 *
 * Populated by ComponentRegistry::discover() from each component directory
 * that contains a fields.php file.
 */
final class ComponentDefinition
{
    public function __construct(
        /** Layout key: the folder name in the format chosen at discovery ('hero', 'module_tabs') */
        public readonly string  $key,
        /** Absolute path to the component directory */
        public readonly string  $path,
        /** Human-readable label, from FlexLayout or auto-derived from key */
        public readonly string  $label,
        /** Dashicons slug or SVG string, from FlexLayout or '' */
        public readonly string  $icon,
        /** Category for grouping in the component picker, from FlexLayout or 'general' */
        public readonly string  $category,
        /** Resolved template path (.blade.php / .twig / .php) or null if none found */
        public readonly ?string $templatePath,
        /** Whether a script.js file exists in the component directory */
        public readonly bool    $hasScript,
        /** Whether a functions.php file exists in the component directory */
        public readonly bool    $hasFunctions,
        /** Folder name as on disk, e.g. 'ModuleTabs' */
        public readonly string  $folder = '',
        /** Absolute path of the fields file ('' when unknown) */
        public readonly string  $fieldsFile = '',
        /** Prefix put before the top-level field keys ('module_tabs_'), '' for none */
        public readonly string  $fieldPrefix = '',
        /** Short text shown in the component picker */
        public readonly string  $description = '',
        /**
         * Prepares a section before it is rendered (the theme's getData()):
         * fn (array $section, ComponentDefinition $component): array
         */
        public readonly ?\Closure $data = null,
        /**
         * Turns what fields.php returned into the layout's fields (shared
         * fields, …): fn (array $fields, ComponentDefinition $component): array
         */
        public readonly ?\Closure $fields = null,
    ) {}
}
