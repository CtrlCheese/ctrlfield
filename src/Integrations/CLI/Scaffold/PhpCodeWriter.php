<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI\Scaffold;

use FieldForge\Enums\FieldType;
use FieldForge\Integrations\CLI\Scaffold\Exceptions\FileExistsException;

final class PhpCodeWriter
{
    public function writeCpt(CptScaffoldDef $def): string
    {
        $date    = date('Y-m-d');
        $slug    = $def->slug;
        $lines   = [];

        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = " * FieldForge — CPT Registration: {$slug}";
        $lines[] = " * Generated: {$date}";
        $lines[] = ' * Edit freely. This file is the source of truth.';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';

        $uses = ['FieldForge\\Builder\\CPT'];
        if (! empty($def->fields)) {
            $uses[] = 'FieldForge\\Fields\\Field';
        }
        foreach ($uses as $use) {
            $lines[] = "use {$use};";
        }
        $lines[] = '';

        $supportsPhp = $this->arrayToPhpInline($def->supports);
        $lines[] = "CPT::make('{$slug}')";
        $lines[] = "    ->label('{$def->singularLabel}', '{$def->pluralLabel}')";
        $lines[] = "    ->supports({$supportsPhp})";
        if ($def->icon !== 'dashicons-admin-post') {
            $lines[] = "    ->menuIcon('{$def->icon}')";
        }

        if ($def->noRegister) {
            $lines[count($lines) - 1] .= ';';
        } else {
            $lines[] = '    ->register();';
        }

        if (! empty($def->fields)) {
            $lines[] = '';
            $groupSlug = $slug . '_fields';
            $groupTitle = ucwords(str_replace('_', ' ', $slug)) . ' Fields';
            $lines[] = "Field::group('{$groupSlug}')";
            $lines[] = "    ->title('{$groupTitle}')";
            $lines[] = "    ->where('post_type', '==', '{$slug}')";
            $lines[] = "    ->fields([";
            $lines   = array_merge($lines, $this->renderFields($def->fields));
            $lines[] = '    ])';
            $lines[] = '    ->register();';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    public function writeTaxonomy(TaxonomyScaffoldDef $def): string
    {
        $date  = date('Y-m-d');
        $slug  = $def->slug;
        $lines = [];

        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = " * FieldForge — Taxonomy Registration: {$slug}";
        $lines[] = " * Generated: {$date}";
        $lines[] = ' * Edit freely. This file is the source of truth.';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';

        $uses = ['FieldForge\\Builder\\Taxonomy'];
        if (! empty($def->fields)) {
            $uses[] = 'FieldForge\\Fields\\Field';
        }
        foreach ($uses as $use) {
            $lines[] = "use {$use};";
        }
        $lines[] = '';

        $attachPhp = $this->arrayToPhpInline($def->attachTo);
        $lines[] = "Taxonomy::make('{$slug}')";
        $lines[] = "    ->label('{$def->singularLabel}', '{$def->pluralLabel}')";
        $lines[] = "    ->attachTo({$attachPhp})";

        if ($def->hierarchical) {
            $lines[] = '    ->hierarchical()';
        }

        if ($def->noRegister) {
            $lines[count($lines) - 1] .= ';';
        } else {
            $lines[] = '    ->register();';
        }

        if (! empty($def->fields)) {
            $lines[] = '';
            $groupSlug  = $slug . '_term_fields';
            $groupTitle = ucwords(str_replace('_', ' ', $slug)) . ' Term Fields';
            $lines[] = "Field::group('{$groupSlug}')";
            $lines[] = "    ->title('{$groupTitle}')";
            $lines[] = "    ->where('taxonomy', '==', '{$slug}')";
            $lines[] = "    ->fields([";
            $lines   = array_merge($lines, $this->renderFields($def->fields));
            $lines[] = '    ])';
            $lines[] = '    ->register();';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    public function writeOptionsPage(OptionsPageScaffoldDef $def): string
    {
        $date  = date('Y-m-d');
        $slug  = $def->slug;
        $lines = [];

        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = " * FieldForge — Options Page Registration: {$slug}";
        $lines[] = " * Generated: {$date}";
        $lines[] = ' * Edit freely. This file is the source of truth.';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';

        $uses = ['FieldForge\\Builder\\OptionsPage'];
        if (! empty($def->fields)) {
            $uses[] = 'FieldForge\\Fields\\Field';
        }
        foreach ($uses as $use) {
            $lines[] = "use {$use};";
        }
        $lines[] = '';

        $lines[] = "OptionsPage::make('{$slug}')";
        $lines[] = "    ->title('{$def->title}')";

        if ($def->capability !== 'manage_options') {
            $lines[] = "    ->capability('{$def->capability}')";
        }

        if ($def->parent !== '') {
            $lines[] = "    ->parent('{$def->parent}')";
        }

        if (! empty($def->fields)) {
            $lines[] = '    ->fields([';
            $lines   = array_merge($lines, $this->renderFields($def->fields, '    '));
            $lines[] = '    ])';
        }

        if ($def->noRegister) {
            $lines[count($lines) - 1] .= ';';
        } else {
            $lines[] = '    ->register();';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    public function writeFieldGroup(FieldGroupScaffoldDef $def): string
    {
        $date  = date('Y-m-d');
        $slug  = $def->slug;
        $lines = [];

        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = " * FieldForge — Field Group Registration: {$slug}";
        $lines[] = " * Generated: {$date}";
        $lines[] = ' * Auto-loaded if placed in FIELDFORGE_SCHEMA_PATH directory.';
        $lines[] = ' * Otherwise: require_once __DIR__ . \'/' . basename($slug) . '.php\'; in your functions.php';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';
        $lines[] = 'use FieldForge\\Fields\\Field;';
        $lines[] = '';

        $groupKey = str_replace('-', '_', $slug);
        $lines[]  = "Field::group('{$groupKey}')";
        $lines[]  = "    ->title('{$def->title}')";

        if ($def->postType !== null) {
            $lines[] = "    ->where('post_type', '==', '{$def->postType}')";
        } elseif ($def->taxonomy !== null) {
            $lines[] = "    ->where('taxonomy', '==', '{$def->taxonomy}')";
        } elseif ($def->optionsPage !== null) {
            $lines[] = "    ->where('options_page', '==', '{$def->optionsPage}')";
        } elseif ($def->context !== null) {
            $lines[] = "    ->where('context', '==', '{$def->context}')";
        }

        if (! empty($def->fields)) {
            $lines[] = '    ->fields([';
            $lines   = array_merge($lines, $this->renderFields($def->fields));
            $lines[] = '    ])';
        }

        if ($def->noRegister) {
            $lines[count($lines) - 1] .= ';';
        } else {
            $lines[] = '    ->register();';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    public function writeContentTable(ContentTableScaffoldDef $def): string
    {
        $date  = date('Y-m-d');
        $slug  = $def->slug;
        $lines = [];

        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = " * FieldForge Pro — ContentTable Registration: {$slug}";
        $lines[] = " * Generated: {$date}";
        $lines[] = ' * Edit freely. This file is the source of truth.';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';
        $lines[] = 'use FieldForgePro\\Builder\\ContentTable;';

        if (! empty($def->fields)) {
            $lines[] = 'use FieldForge\\Fields\\Field;';
        }

        $lines[] = '';
        $lines[] = "ContentTable::make('{$slug}')";
        $lines[] = "    ->label('{$def->singularLabel}', '{$def->pluralLabel}')";

        if ($def->timestamps) {
            $lines[] = '    ->timestamps()';
        }

        if (! empty($def->fields)) {
            $lines[] = '    ->fields([';
            $lines   = array_merge($lines, $this->renderFields($def->fields));
            $lines[] = '    ])';
        }

        if ($def->noRegister) {
            $lines[count($lines) - 1] .= ';';
        } else {
            $lines[] = '    ->register();';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Write generated code to a file.
     *
     * @throws FileExistsException if $path exists and $force is false
     */
    public function writeFile(string $code, string $path, bool $force): void
    {
        if (file_exists($path) && ! $force) {
            throw new FileExistsException(
                "File '{$path}' already exists. Use --force to overwrite."
            );
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $code);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<int, FieldScaffoldDef> $fields */
    private function renderFields(array $fields, string $baseIndent = ''): array
    {
        $lines = [];

        foreach ($fields as $field) {
            if ($field->type === FieldType::GROUP || $field->type === FieldType::REPEATER || $field->type === FieldType::FLEXIBLE_CONTENT) {
                $lines[] = "{$baseIndent}        // TODO: define sub-fields manually";
                $lines[] = "{$baseIndent}        Field::{$field->type->value}('{$field->key}')";
                $lines[] = "{$baseIndent}            ->label('{$field->label}'),";
            } else {
                $lines[] = "{$baseIndent}        Field::{$field->type->value}('{$field->key}')";
                $lines[] = "{$baseIndent}            ->label('{$field->label}'),";
            }
        }

        return $lines;
    }

    /** @param array<int, string> $items */
    private function arrayToPhpInline(array $items): string
    {
        $quoted = array_map(static fn (string $v) => "'{$v}'", $items);
        return '[' . implode(', ', $quoted) . ']';
    }
}
