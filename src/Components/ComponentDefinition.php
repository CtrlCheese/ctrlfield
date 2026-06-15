<?php

declare(strict_types=1);

namespace FieldForge\Components;

/**
 * Value object representing a discovered component.
 *
 * Populated by ComponentRegistry::discover() from each component directory
 * that contains a fields.php file.
 */
final class ComponentDefinition
{
    public function __construct(
        /** Lowercased directory name, e.g. 'hero' */
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
    ) {}
}
