<?php

declare(strict_types=1);

namespace CtrlField\Admin\PostTypes;

/**
 * Turns an admin-created post type into the equivalent CPT::make() schema
 * code, so a site can move from "made in the panel" to "versioned in git".
 */
final class PostTypeCodeExporter
{
    public function export(PostTypeDefinition $def): string
    {
        $lines = [
            "CPT::make({$this->str($def->slug)})",
            "    ->label({$this->str($def->singular)}, {$this->str($def->plural)})",
            "    ->menuIcon({$this->str($def->icon)})",
            '    ->supports([' . implode(', ', array_map($this->str(...), $def->supports)) . '])',
            '    ->public(' . $this->bool($def->public) . ')',
            '    ->hierarchical(' . $this->bool($def->hierarchical) . ')',
            '    ->hasArchive(' . $this->bool($def->hasArchive) . ')',
            '    ->showInRest(' . $this->bool($def->showInRest) . ')',
            "    ->menuPosition({$def->menuPosition})",
        ];

        if ($def->rewriteSlug !== '') {
            $lines[] = "    ->rewriteSlug({$this->str($def->rewriteSlug)})";
        }
        if ($def->description !== '') {
            $lines[] = "    ->description({$this->str($def->description)})";
        }

        $lines[] = '    ->register();';

        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "use CtrlField\\Builder\\CPT;\n\n"
            . "// Exported from CtrlField → Post Types. Once this file is in your schema\n"
            . "// directory, delete the post type in the admin: code takes precedence.\n"
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
