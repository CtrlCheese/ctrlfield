<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI\Commands;

use CtrlField\Admin\Inspector\ComponentReport;
use CtrlField\Admin\Inspector\InspectorData;
use CtrlField\Admin\Inspector\SchemaAnalyzer;
use CtrlField\Admin\FieldGroups\FieldGroupsServiceProvider;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\SchemaErrors;

/**
 * WP-CLI command: wp ctrlfield components
 *
 * The Schema Inspector's Components tab in the terminal: the components
 * ComponentRegistry discovered and their problems (ComponentReport — the
 * theme adds its own checks with the ctrlfield/component_issues filter).
 *
 * Excluded from PHPStan — references WP_CLI, which is not available outside the CLI runtime.
 */
class ComponentsCommand
{
    /**
     * Lists the discovered components.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv, yaml or count.
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp ctrlfield components list
     *     wp ctrlfield components list --format=json
     *
     * @subcommand list
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function list_(array $args, array $assocArgs): void
    {
        $report = $this->report();
        $usedBy = (new InspectorData())->layoutUsage();

        $rows = [];
        foreach ($report as $key => $item) {
            $c      = $item['component'];
            $errors = count(array_filter($item['issues'], static fn (array $i): bool => $i['level'] === 'error'));
            $rows[] = [
                'key'      => $key,
                'label'    => $c->label,
                'category' => $c->category,
                'folder'   => $c->folder,
                'files'    => implode(', ', $item['files']),
                'fields'   => SchemaAnalyzer::count($item['fields']),
                'posts'    => count($usedBy[$key] ?? []),
                'errors'   => $errors,
                'warnings' => count($item['issues']) - $errors,
            ];
        }

        \WP_CLI\Utils\format_items(
            $assocArgs['format'] ?? 'table',
            $rows,
            ['key', 'label', 'category', 'folder', 'files', 'fields', 'posts', 'errors', 'warnings']
        );
    }

    /**
     * Reports the problems of every component. Exits with an error when a
     * component has one (or a warning, with --strict) — for CI.
     *
     * ## OPTIONS
     *
     * [--strict]
     * : Fail on warnings too.
     *
     * ## EXAMPLES
     *
     *     wp ctrlfield components validate
     *     wp ctrlfield components validate --strict
     *
     * @param array<int, string>    $args
     * @param array<string, string> $assocArgs
     */
    public function validate(array $args, array $assocArgs): void
    {
        $report = $this->report();
        if ($report === []) {
            \WP_CLI::warning('No components discovered.');
            return;
        }

        $errors   = 0;
        $warnings = 0;
        foreach ($report as $key => $item) {
            if ($item['issues'] === []) {
                \WP_CLI::log(\WP_CLI::colorize("%G✓%n {$key}"));
                continue;
            }
            $hasError = in_array('error', array_column($item['issues'], 'level'), true);
            \WP_CLI::log(\WP_CLI::colorize(($hasError ? '%R✗%n ' : '%Y△%n ') . $key));
            foreach ($item['issues'] as $issue) {
                \WP_CLI::log('    ' . strtoupper($issue['level']) . ': ' . $issue['message']);
                $issue['level'] === 'error' ? $errors++ : $warnings++;
            }
        }

        $summary = sprintf('%d component(s): %d error(s), %d warning(s).', count($report), $errors, $warnings);
        if ($errors > 0 || ($warnings > 0 && isset($assocArgs['strict']))) {
            \WP_CLI::error($summary);
        }
        $warnings > 0 ? \WP_CLI::warning($summary) : \WP_CLI::success($summary);
    }

    /** @return array<string, array{component: \CtrlField\Components\ComponentDefinition, files: list<string>, screenshot: string, fields: list<array<string, mixed>>, issues: list<array{level: string, message: string}>}> */
    private function report(): array
    {
        $groups   = FieldRegistry::all();
        $analyzer = new SchemaAnalyzer($groups, FieldGroupsServiceProvider::registered());

        return (new ComponentReport($groups, SchemaErrors::all(), $analyzer->issues()))->all();
    }
}
