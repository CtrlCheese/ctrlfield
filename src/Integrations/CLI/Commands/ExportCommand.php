<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Commands;

use CtrlField\Builder\CPT;
use CtrlField\Builder\OptionsPage;
use CtrlField\Builder\Taxonomy;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Registry\FieldRegistry;

/**
 * WP-CLI command: wp ctrlfield export
 *
 * Excluded from PHPStan — references WP_CLI not available outside CLI runtime.
 *
 * Usage:
 *   wp ctrlfield export
 *   wp ctrlfield export --file=<path>
 */
class ExportCommand
{
    /**
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $assocArgs['file'] ?? null;

        $export = [
            'generated_at'   => gmdate('c'),
            'schema_version' => SchemaVersion::CURRENT,
            'cpts'           => $this->exportCpts(),
            'taxonomies'     => $this->exportTaxonomies(),
            'field_groups'   => $this->exportFieldGroups(),
            'options_pages'  => $this->exportOptionsPages(),
        ];

        $json = json_encode($export, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($file !== null) {
            if (file_put_contents($file, $json) === false) {
                \WP_CLI::error(sprintf('Could not write to file: %s', $file));
                return;
            }
            \WP_CLI::success(sprintf('Schema exported to %s', $file));
        } else {
            \WP_CLI::log($json);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function exportCpts(): array
    {
        $result = [];

        foreach (CPT::all() as $cpt) {
            $result[] = [
                'post_type' => $cpt->getPostType(),
                'singular'  => $cpt->getSingularLabel(),
                'plural'    => $cpt->getPluralLabel(),
                'icon'      => $cpt->getMenuIcon(),
                'supports'  => $cpt->getSupports(),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function exportTaxonomies(): array
    {
        $result = [];

        foreach (Taxonomy::all() as $tax) {
            $result[] = [
                'taxonomy'     => $tax->getTaxonomy(),
                'singular'     => $tax->getSingularLabel(),
                'plural'       => $tax->getPluralLabel(),
                'hierarchical' => $tax->isHierarchical(),
                'attach_to'    => $tax->getAttachTo(),
                'term_fields'  => array_map(
                    static fn($f) => $f->getDefinition(),
                    $tax->getTermFields()
                ),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function exportFieldGroups(): array
    {
        $result = [];

        foreach (FieldRegistry::all() as $group) {
            $result[] = [
                'key'            => $group->getKey(),
                'title'          => $group->getTitle(),
                'and_conditions' => $group->getAndConditions(),
                'or_groups'      => $group->getOrGroups(),
                'fields'         => array_map(
                    static fn($f) => $f->getDefinition(),
                    $group->getFields()
                ),
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function exportOptionsPages(): array
    {
        $result = [];

        foreach (OptionsPage::all() as $page) {
            $result[] = [
                'key'        => $page->getKey(),
                'title'      => $page->getTitle(),
                'menu_slug'  => $page->getMenuSlug(),
                'capability' => $page->getCapability(),
                'parent'     => $page->getParent(),
                'fields'     => array_map(
                    static fn($f) => $f->getDefinition(),
                    $page->getFields()
                ),
            ];
        }

        return $result;
    }
}
