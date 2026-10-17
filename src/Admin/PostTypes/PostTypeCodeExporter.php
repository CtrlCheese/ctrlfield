<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * Turns admin-created post types and taxonomies into the equivalent
 * CPT::make() / Taxonomy::make() schema code, so a site can move from
 * "made in the panel" to "versioned in git". The code registers exactly
 * what PostTypesServiceProvider registers.
 */
final class PostTypeCodeExporter
{
    public function export(PostTypeDefinition $def): string
    {
        return $this->file('CPT', $this->cptLines($def));
    }

    public function exportTaxonomy(TaxonomyDefinition $def): string
    {
        return $this->file('Taxonomy', $this->taxonomyLines($def));
    }

    /** @return list<string> */
    private function cptLines(PostTypeDefinition $def): array
    {
        $archive = $def->archive();
        $lines   = [
            "CPT::make({$this->str($def->slug)})",
            "    ->label({$this->str($def->singular)}, {$this->str($def->plural)})",
            "    ->menuIcon({$this->str($def->icon)})",
            '    ->supports([' . implode(', ', array_map($this->str(...), $def->supports)) . '])',
            '    ->public(' . $this->bool($def->public) . ')',
            '    ->publiclyQueryable(' . $this->bool($def->public) . ')',
            '    ->excludeFromSearch(' . $this->bool($def->excludeFromSearch) . ')',
            '    ->hierarchical(' . $this->bool($def->hierarchical) . ')',
            '    ->hasArchive(' . (is_string($archive) ? $this->str($archive) : $this->bool($archive)) . ')',
            '    ->showInRest(' . $this->bool($def->showInRest) . ')',
            "    ->menuPosition({$def->menuPosition})",
        ];

        if ($def->rewriteSlug !== '') {
            $lines[] = "    ->rewriteSlug({$this->str($def->rewriteSlug)})";
        }
        if ($def->restBase !== '') {
            $lines[] = "    ->restBase({$this->str($def->restBase)})";
        }
        if ($def->capabilityType !== 'post') {
            $lines[] = "    ->capability({$this->str($def->capabilityType)})";
        }
        if ($def->taxonomies !== []) {
            $lines[] = '    ->taxonomies([' . implode(', ', array_map($this->str(...), $def->taxonomies)) . '])';
        }
        if ($def->description !== '') {
            $lines[] = "    ->description({$this->str($def->description)})";
        }

        return $lines;
    }

    /** @return list<string> */
    private function taxonomyLines(TaxonomyDefinition $def): array
    {
        $lines = [
            "Taxonomy::make({$this->str($def->slug)})",
            "    ->label({$this->str($def->singular)}, {$this->str($def->plural)})",
            '    ->attachTo([' . implode(', ', array_map($this->str(...), $def->postTypes)) . '])',
            '    ->hierarchical(' . $this->bool($def->hierarchical) . ')',
            '    ->public(' . $this->bool($def->public) . ')',
            '    ->showInRest(' . $this->bool($def->showInRest) . ')',
            '    ->showAdminColumn(' . $this->bool($def->showAdminColumn) . ')',
        ];
        if ($def->rewriteSlug !== '') {
            $lines[] = "    ->rewriteSlug({$this->str($def->rewriteSlug)})";
        }
        if ($def->description !== '') {
            $lines[] = "    ->description({$this->str($def->description)})";
        }

        return $lines;
    }

    /** @param list<string> $lines */
    private function file(string $class, array $lines): string
    {
        $lines[] = '    ->register();';

        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "defined('ABSPATH') || exit;\n\n"
            . "use CtrlField\\Builder\\{$class};\n\n"
            . "// Exported from CtrlField → Post Types. Once this file is in your schema\n"
            . "// directory, delete it in the admin: code takes precedence.\n"
            . implode("\n", $lines) . "\n";
    }

    private function str(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
