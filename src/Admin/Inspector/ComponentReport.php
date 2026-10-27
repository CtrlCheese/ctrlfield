<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Builder\FieldGroup;
use CtrlField\Components\ComponentDefinition;
use CtrlField\Components\ComponentRegistry;

/**
 * What the Schema Inspector's Components tab and `wp ctrlfield components`
 * show for each component ComponentRegistry discovered: its files and
 * screenshot, its fields, the posts using it and its problems.
 *
 * Problems are a fields file that did not load, a missing template, the
 * schema problems inside the component's layout (conditions, labels), and
 * whatever the theme adds through the ctrlfield/component_issues filter — the
 * conventions of its own component files:
 *
 *   add_filter('ctrlfield/component_issues', function (array $issues, ComponentDefinition $c): array {
 *       if (! is_file($c->path . '/' . $c->folder . '.php')) {
 *           $issues[] = ['level' => 'error', 'message' => 'No component class.'];
 *       }
 *       return $issues;
 *   }, 10, 2);
 */
final class ComponentReport
{
    private const SCREENSHOTS = ['screenshot.png', 'screenshot.jpg', 'screenshot.webp'];

    /** @var array<string, list<array<string, mixed>>>|null layout key => field tree, built once */
    private ?array $trees = null;

    /**
     * @param array<string, FieldGroup>         $groups       FieldRegistry::all()
     * @param array<string, string>             $schemaErrors SchemaErrors::all() — file => message
     * @param list<array<string, string>>       $schemaIssues SchemaAnalyzer::issues()
     */
    public function __construct(
        private readonly array $groups,
        private readonly array $schemaErrors = [],
        private readonly array $schemaIssues = [],
    ) {}

    /**
     * Every discovered component, keyed by its layout key.
     *
     * @return array<string, array{component: ComponentDefinition, files: list<string>, screenshot: string, fields: list<array<string, mixed>>, issues: list<array{level: string, message: string}>}>
     */
    public function all(): array
    {
        $out = [];
        foreach (ComponentRegistry::all() as $key => $component) {
            $out[(string) $key] = [
                'component'  => $component,
                'files'      => self::files($component),
                'screenshot' => self::screenshot($component),
                'fields'     => $this->fields((string) $key),
                'issues'     => $this->issues($component),
            ];
        }

        return $out;
    }

    /**
     * The files discovery looks at that the component has.
     *
     * @return list<string>
     */
    public static function files(ComponentDefinition $component): array
    {
        return array_values(array_filter([
            $component->fieldsFile !== '' ? basename($component->fieldsFile) : '',
            $component->templatePath !== null ? basename($component->templatePath) : '',
            // On disk: hasScript is false when the theme loads script.js itself.
            is_file($component->path . '/script.js') ? 'script.js' : '',
            $component->hasFunctions ? 'functions.php' : '',
        ]));
    }

    /** Absolute path of the component's screenshot (the one the component picker shows), '' for none. */
    public static function screenshot(ComponentDefinition $component): string
    {
        foreach (self::SCREENSHOTS as $name) {
            if (is_file($component->path . '/' . $name)) {
                return $component->path . '/' . $name;
            }
        }

        return '';
    }

    /**
     * The field tree of the component's layout, as registered (keys prefixed,
     * shared fields added) — from the first flexible content that offers it.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(string $key): array
    {
        if ($this->trees === null) {
            $this->trees = [];
            foreach ($this->groups as $group) {
                $this->collect(SchemaAnalyzer::tree(array_values($group->getFields())));
            }
        }

        return $this->trees[$key] ?? [];
    }

    /** @return list<array{level: string, message: string}> errors first */
    public function issues(ComponentDefinition $component): array
    {
        $issues = [];
        $dir    = rtrim($component->path, '/') . '/';
        foreach ($this->schemaErrors as $file => $error) {
            if (str_starts_with((string) $file, $dir)) {
                $issues[] = ['level' => 'error', 'message' => basename((string) $file) . ': ' . $error];
            }
        }
        if ($component->fieldsFile !== '' && ComponentRegistry::loadedFields($component->key) === null && $issues === []) {
            $issues[] = ['level' => 'error', 'message' => sprintf(
                /* translators: %s: file name */
                __('%s returned no fields.', 'ctrlfield'),
                basename($component->fieldsFile)
            )];
        }
        if ($component->templatePath === null) {
            $issues[] = ['level' => 'warning', 'message' => __('No template: the component renders nothing.', 'ctrlfield')];
        }
        foreach ($this->schemaIssues as $issue) {
            if (($issue['layout'] ?? '') === $component->key && $issue['level'] !== 'info') {
                $issues[] = ['level' => $issue['level'], 'message' => $issue['message']];
            }
        }

        $issues = function_exists('apply_filters')
            ? (array) apply_filters('ctrlfield/component_issues', $issues, $component)
            : $issues;

        $clean = [];
        foreach ($issues as $issue) {
            if (is_array($issue) && isset($issue['message']) && (string) $issue['message'] !== '') {
                $clean[] = [
                    'level'   => ($issue['level'] ?? '') === 'error' ? 'error' : 'warning',
                    'message' => (string) $issue['message'],
                ];
            }
        }
        usort($clean, static fn (array $a, array $b): int => ($a['level'] === 'error' ? 0 : 1) <=> ($b['level'] === 'error' ? 0 : 1));

        return $clean;
    }

    /** @param list<array<string, mixed>> $tree */
    private function collect(array $tree): void
    {
        foreach ($tree as $node) {
            foreach ($node['layouts'] as $layout) {
                $this->trees[$layout['key']] ??= $layout['fields'];
                $this->collect($layout['fields']);
            }
            $this->collect($node['children']);
        }
    }
}
