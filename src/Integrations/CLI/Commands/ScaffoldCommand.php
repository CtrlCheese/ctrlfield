<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Commands;

use CtrlField\Integrations\CLI\Scaffold\ContentTableScaffoldDef;
use CtrlField\Integrations\CLI\Scaffold\CptScaffoldDef;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\FileExistsException;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\InvalidSlugException;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\UnknownFieldTypeException;
use CtrlField\Integrations\CLI\Scaffold\FieldGroupScaffoldDef;
use CtrlField\Integrations\CLI\Scaffold\FieldScaffoldDef;
use CtrlField\Integrations\CLI\Scaffold\OptionsPageScaffoldDef;
use CtrlField\Integrations\CLI\Scaffold\PhpCodeWriter;
use CtrlField\Integrations\CLI\Scaffold\TaxonomyScaffoldDef;

/**
 * WP-CLI command: wp ctrlfield scaffold
 *
 * Excluded from PHPStan — references WP_CLI which is not available outside CLI runtime.
 *
 * @when before_wp_load
 */
class ScaffoldCommand
{
    /**
     * Scaffold a Custom Post Type registration.
     *
     * ## OPTIONS
     *
     * <slug>
     * : The post type slug (lowercase, underscores).
     *
     * [--label=<singular,plural>]
     * : Comma-separated singular and plural labels. Default: title-cased slug.
     *
     * [--supports=<supports>]
     * : Comma-separated list. Default: title,editor,thumbnail.
     *
     * [--icon=<dashicon>]
     * : Dashicon slug. Default: dashicons-admin-post.
     *
     * [--fields=<field_definitions>]
     * : Comma-separated field definitions: key:type[:label].
     *
     * [--write=<path>]
     * : Write output to a file instead of stdout.
     *
     * [--force]
     * : Overwrite existing file (used with --write).
     *
     * [--no-register]
     * : Omit the ->register() call.
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function post_type(array $args, array $assocArgs): void
    {
        $slug = $args[0] ?? '';

        try {
            $this->assertValidSlug($slug);
            $fields = $this->parseFields($assocArgs['fields'] ?? '');
        } catch (InvalidSlugException | UnknownFieldTypeException $e) {
            \WP_CLI::error($e->getMessage());
        }

        [$singular, $plural] = $this->parseLabel($assocArgs['label'] ?? '', $slug);
        $supports    = $this->parseSupports($assocArgs['supports'] ?? 'title,editor,thumbnail');
        $icon        = $assocArgs['icon'] ?? 'dashicons-admin-post';
        $noRegister  = isset($assocArgs['no-register']);

        $def    = new CptScaffoldDef($slug, $singular, $plural, $icon, $supports, $fields, $noRegister);
        $writer = new PhpCodeWriter();
        $code   = $writer->writeCpt($def);

        $this->output($writer, $code, $assocArgs);
    }

    /**
     * Scaffold a Taxonomy registration.
     *
     * ## OPTIONS
     *
     * <slug>
     * : The taxonomy slug (lowercase, underscores).
     *
     * [--attach-to=<post_types>]
     * : Comma-separated post type slugs.
     *
     * [--label=<singular,plural>]
     * : Comma-separated singular and plural labels. Default: title-cased slug.
     *
     * [--hierarchical]
     * : Register as hierarchical (category-like).
     *
     * [--fields=<field_definitions>]
     * : Comma-separated field definitions: key:type[:label].
     *
     * [--write=<path>]
     * [--force]
     * [--no-register]
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function taxonomy(array $args, array $assocArgs): void
    {
        $slug = $args[0] ?? '';

        try {
            $this->assertValidSlug($slug);
            $fields = $this->parseFields($assocArgs['fields'] ?? '');
        } catch (InvalidSlugException | UnknownFieldTypeException $e) {
            \WP_CLI::error($e->getMessage());
        }

        [$singular, $plural] = $this->parseLabel($assocArgs['label'] ?? '', $slug);
        $attachTo   = array_filter(array_map('trim', explode(',', $assocArgs['attach-to'] ?? '')));
        $hierarchical = isset($assocArgs['hierarchical']);
        $noRegister   = isset($assocArgs['no-register']);

        $def    = new TaxonomyScaffoldDef($slug, $singular, $plural, array_values($attachTo), $hierarchical, $fields, $noRegister);
        $writer = new PhpCodeWriter();
        $code   = $writer->writeTaxonomy($def);

        $this->output($writer, $code, $assocArgs);
    }

    /**
     * Scaffold an Options Page registration.
     *
     * ## OPTIONS
     *
     * <slug>
     * : The page key (lowercase, underscores).
     *
     * [--title=<title>]
     * : Page title. Default: title-cased slug.
     *
     * [--capability=<cap>]
     * : Required capability. Default: manage_options.
     *
     * [--parent=<menu_slug>]
     * : If set, registers as a submenu page.
     *
     * [--fields=<field_definitions>]
     * : Comma-separated field definitions: key:type[:label].
     *
     * [--write=<path>]
     * [--force]
     * [--no-register]
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function options_page(array $args, array $assocArgs): void
    {
        $slug = $args[0] ?? '';

        try {
            $this->assertValidSlug($slug);
            $fields = $this->parseFields($assocArgs['fields'] ?? '');
        } catch (InvalidSlugException | UnknownFieldTypeException $e) {
            \WP_CLI::error($e->getMessage());
        }

        $title      = $assocArgs['title'] ?? $this->titleCase($slug);
        $capability = $assocArgs['capability'] ?? 'manage_options';
        $parent     = $assocArgs['parent'] ?? '';
        $noRegister = isset($assocArgs['no-register']);

        $def    = new OptionsPageScaffoldDef($slug, $title, $capability, $parent, $fields, $noRegister);
        $writer = new PhpCodeWriter();
        $code   = $writer->writeOptionsPage($def);

        $this->output($writer, $code, $assocArgs);
    }

    /**
     * Scaffold a Field Group.
     *
     * ## OPTIONS
     *
     * <slug>
     * : The group key (lowercase, underscores, or dashes).
     *
     * [--post-type=<post_type>]
     * [--taxonomy=<taxonomy>]
     * [--context=<user_profile|comment>]
     * [--options-page=<page_slug>]
     *
     * [--title=<title>]
     * : Group title. Default: title-cased slug.
     *
     * [--fields=<field_definitions>]
     * : Comma-separated field definitions: key:type[:label].
     *
     * [--write=<path>]
     * [--force]
     * [--no-register]
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function field_group(array $args, array $assocArgs): void
    {
        $slug = $args[0] ?? '';

        if ($slug === '') {
            \WP_CLI::error('Please provide a slug.');
        }

        try {
            $fields = $this->parseFields($assocArgs['fields'] ?? '');
        } catch (InvalidSlugException | UnknownFieldTypeException $e) {
            \WP_CLI::error($e->getMessage());
        }

        $title       = $assocArgs['title'] ?? $this->titleCase(str_replace('-', '_', $slug));
        $postType    = $assocArgs['post-type'] ?? null;
        $taxonomy    = $assocArgs['taxonomy'] ?? null;
        $optionsPage = $assocArgs['options-page'] ?? null;
        $context     = $assocArgs['context'] ?? null;
        $noRegister  = isset($assocArgs['no-register']);

        $def    = new FieldGroupScaffoldDef($slug, $title, $postType, $taxonomy, $optionsPage, $context, $fields, $noRegister);
        $writer = new PhpCodeWriter();
        $code   = $writer->writeFieldGroup($def);

        $this->output($writer, $code, $assocArgs);
    }

    /**
     * Scaffold a ContentTable registration (Pro).
     *
     * ## OPTIONS
     *
     * <slug>
     * : The table name (lowercase, underscores, no prefix).
     *
     * [--label=<singular,plural>]
     * : Comma-separated singular and plural labels.
     *
     * [--fields=<field_definitions>]
     * : Comma-separated field definitions: key:type[:label].
     *
     * [--timestamps]
     * : Include created_at / updated_at columns (default: yes).
     *
     * [--no-timestamps]
     * : Omit timestamp columns.
     *
     * [--write=<path>]
     * [--force]
     * [--no-register]
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function content_table(array $args, array $assocArgs): void
    {
        $slug = $args[0] ?? '';

        try {
            $this->assertValidSlug($slug);
            $fields = $this->parseFields($assocArgs['fields'] ?? '');
        } catch (InvalidSlugException | UnknownFieldTypeException $e) {
            \WP_CLI::error($e->getMessage());
        }

        [$singular, $plural] = $this->parseLabel($assocArgs['label'] ?? '', $slug);
        $timestamps = ! isset($assocArgs['no-timestamps']);
        $noRegister = isset($assocArgs['no-register']);

        $def    = new ContentTableScaffoldDef($slug, $singular, $plural, $timestamps, $fields, $noRegister);
        $writer = new PhpCodeWriter();
        $code   = $writer->writeContentTable($def);

        $this->output($writer, $code, $assocArgs);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @throws InvalidSlugException */
    private function assertValidSlug(string $slug): void
    {
        if ($slug === '' || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
            throw new InvalidSlugException(
                "Slug '{$slug}' is invalid. Only lowercase letters, digits, and underscores are allowed."
            );
        }
    }

    /**
     * @return array<int, FieldScaffoldDef>
     * @throws InvalidSlugException
     * @throws UnknownFieldTypeException
     */
    private function parseFields(string $raw): array
    {
        return FieldScaffoldDef::parseMany($raw);
    }

    /** @return array<int, string> */
    private function parseSupports(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @return array{string, string} */
    private function parseLabel(string $raw, string $slug): array
    {
        if ($raw === '') {
            $singular = $this->titleCase($slug);
            $plural   = $singular . 's';
            return [$singular, $plural];
        }

        $parts = explode(',', $raw, 2);
        return [trim($parts[0]), trim($parts[1] ?? $parts[0] . 's')];
    }

    private function titleCase(string $slug): string
    {
        return ucwords(str_replace('_', ' ', $slug));
    }

    /**
     * Write code to stdout or a file, then report result.
     *
     * @param array<string, string> $assocArgs
     */
    private function output(PhpCodeWriter $writer, string $code, array $assocArgs): void
    {
        $writePath = $assocArgs['write'] ?? null;
        $force     = isset($assocArgs['force']);

        if ($writePath === null) {
            \WP_CLI::line($code);
            return;
        }

        try {
            $writer->writeFile($code, $writePath, $force);
            \WP_CLI::success("Written to {$writePath}");
        } catch (FileExistsException $e) {
            \WP_CLI::error($e->getMessage());
        }
    }
}
