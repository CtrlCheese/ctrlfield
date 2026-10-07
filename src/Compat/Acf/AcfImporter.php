<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Admin\FieldGroups\FieldGroupRepository;
use CtrlField\Data\FieldWriter;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\JsonGroup;

/**
 * Imports ACF field groups (and optionally their saved values) into CtrlField.
 *
 * Groups are read from the database (works with ACF deactivated) and from
 * acf-json folders. ACF's own data is only read: nothing is changed or
 * deleted, so ACF can be reactivated at any time.
 */
final class AcfImporter
{
    public const GROUP_MAP_OPTION = 'ctrlfield_acf_group_map';

    public function __construct(private readonly FieldGroupRepository $repo) {}

    /**
     * ACF groups found on this site, keyed by ACF group key.
     *
     * @return array<string, array{acf: array<string, mixed>, source: string, imported: ?string}>
     */
    public function sources(): array
    {
        $map    = $this->groupMap();
        $groups = [];

        foreach ($this->databaseGroups() as $key => $acf) {
            $groups[$key] = ['acf' => $acf, 'source' => 'database', 'imported' => $map[$key] ?? null];
        }
        // Local JSON wins over the database copy, as in ACF.
        foreach ($this->jsonGroups() as $key => $acf) {
            $groups[$key] = ['acf' => $acf, 'source' => 'json', 'imported' => $map[$key] ?? null];
        }

        uasort($groups, static fn ($a, $b) => strcasecmp((string) ($a['acf']['title'] ?? ''), (string) ($b['acf']['title'] ?? '')));

        return $groups;
    }

    /**
     * @param  list<string> $acfKeys
     * @return list<array{title: string, key: string, status: string, warnings: list<string>, errors: list<string>, objects: int, skipped: list<string>}>
     */
    public function import(array $acfKeys, bool $withData, bool $dryRun = false): array
    {
        $sources  = $this->sources();
        $groupMap = $this->groupMap();
        $keyMap   = (array) get_option(AcfApi::FIELD_KEYS_OPTION, []);
        $report   = [];

        foreach ($acfKeys as $acfKey) {
            if (! isset($sources[$acfKey])) {
                continue;
            }
            $acf   = $sources[$acfKey]['acf'];
            $key   = $groupMap[$acfKey] ?? $this->uniqueKey(AcfConverter::keyFor((string) ($acf['title'] ?? $acfKey)));
            $conv  = (new AcfConverter())->convertGroup($acf, $key);
            [$group, $errors] = JsonGroup::normalize($conv['group']);

            $entry = [
                'title' => (string) ($acf['title'] ?? $acfKey), 'key' => $key, 'status' => 'imported',
                'warnings' => $conv['warnings'], 'errors' => $errors, 'objects' => 0, 'skipped' => [],
            ];

            // Values live in one store per post: a key used by another group means a shared value.
            foreach ($group['fields'] as $field) {
                foreach (FieldRegistry::all() as $other) {
                    if ($other->getKey() === $key) {
                        continue;
                    }
                    foreach ($other->getFields() as $existing) {
                        if ($existing->getKey() === $field['key']) {
                            $entry['warnings'][] = sprintf(__('Field "%1$s" has the same key as a field of the group "%2$s"; on the same post both read and write one value.', 'ctrlfield'), $field['key'], $other->getTitle() ?: $other->getKey());
                        }
                    }
                }
            }

            if ($errors !== []) {
                $entry['status'] = 'failed';
                $report[]        = $entry;
                continue;
            }

            if (! $dryRun) {
                $this->repo->save($group);
                $groupMap[$acfKey] = $key;
                $keyMap            = $conv['fieldKeys'] + $keyMap;
            }

            if ($withData) {
                [$entry['objects'], $entry['skipped']] = $this->copyData($acf, $group, $dryRun);
            }

            $report[] = $entry;
        }

        if (! $dryRun) {
            update_option(self::GROUP_MAP_OPTION, $groupMap, false);
            update_option(AcfApi::FIELD_KEYS_OPTION, $keyMap, false);
        }

        return $report;
    }

    // -------------------------------------------------------------------------
    // Values
    // -------------------------------------------------------------------------

    /**
     * Copy ACF values of one group to CtrlField for every object its location targets.
     *
     * @param  array<string, mixed> $acf
     * @param  array<string, mixed> $group normalized CtrlField group
     * @return array{0: int, 1: list<string>} [objects written, problems]
     */
    private function copyData(array $acf, array $group, bool $dryRun): array
    {
        $defs = [];
        foreach (JsonGroup::build($group)->getFields() as $def) {
            $defs[$def->getKey()] = $def;
        }

        $count    = 0;
        $problems = [];

        foreach ($this->targets($group) as [$type, $id, $get]) {
            $values = [];
            foreach (is_array($acf['fields'] ?? null) ? $acf['fields'] : [] as $acfField) {
                $name = is_array($acfField) ? (string) ($acfField['name'] ?? '') : '';
                $key  = AcfConverter::keyFor($name);
                if ($name === '' || ! isset($defs[$key])) {
                    continue;
                }
                [$found, $raw] = AcfValues::readMeta($acfField, $get, $name);
                if ($found) {
                    $values[$key] = AcfValues::toStorage($raw, $defs[$key]);
                }
            }

            if ($values === []) {
                continue;
            }
            $count++;
            if ($dryRun) {
                continue;
            }

            $skipped = FieldWriter::write($type, $id, $values, true, $defs);
            foreach ($skipped as $field => $why) {
                $problems[] = sprintf('%s #%s, %s: %s', $type, (string) $id, $field, $why);
            }
        }

        return [$count, array_slice($problems, 0, 50)];
    }

    /**
     * Objects the group's location points at, with a meta getter each.
     *
     * @param  array<string, mixed> $group
     * @return iterable<array{0: string, 1: int|string, 2: callable(string): mixed}>
     */
    private function targets(array $group): iterable
    {
        $rules     = array_merge($group['location'], $group['locationAny']);
        $postTypes = [];
        $postRules = false;

        foreach ($rules as $rule) {
            if ($rule['operator'] !== '==') {
                continue;
            }
            switch ($rule['key']) {
                case 'post_type':
                    $postTypes[] = $rule['value'];
                    break;
                case 'taxonomy':
                    $terms = get_terms(['taxonomy' => $rule['value'], 'hide_empty' => false, 'fields' => 'ids']);
                    foreach (is_array($terms) ? $terms : [] as $termId) {
                        $termId = (int) $termId;
                        yield ['term', $termId, $this->metaGetter('term', $termId)];
                    }
                    break;
                case 'options_page':
                    $page = $rule['value'];
                    yield ['options', $page, static function (string $name): mixed {
                        $v = get_option('options_' . $name, null);
                        return $v === null || $v === false ? null : $v;
                    }];
                    break;
                case 'user_role':
                    foreach (get_users(['role' => $rule['value'], 'fields' => 'ID']) as $userId) {
                        yield ['user', (int) $userId, $this->metaGetter('user', (int) $userId)];
                    }
                    break;
                case 'context':
                    if ($rule['value'] === 'user_profile') {
                        foreach (get_users(['fields' => 'ID']) as $userId) {
                            yield ['user', (int) $userId, $this->metaGetter('user', (int) $userId)];
                        }
                    }
                    break;
                default:
                    $postRules = $postRules || in_array($rule['key'], ['page_template', 'post_template', 'page_type', 'post_parent', 'post', 'post_status', 'post_format', 'post_term'], true);
            }
        }

        if ($postTypes === [] && $postRules) {
            $postTypes = ['page', 'post'];
        }

        if ($postTypes !== []) {
            $ids = get_posts([
                'post_type'      => array_values(array_unique($postTypes)),
                'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            foreach ($ids as $postId) {
                yield ['post', (int) $postId, $this->metaGetter('post', (int) $postId)];
            }
        }
    }

    /** @return callable(string): mixed */
    private function metaGetter(string $type, int $id): callable
    {
        return static fn (string $name): mixed => metadata_exists($type, $id, $name)
            ? get_metadata($type, $id, $name, true)
            : null;
    }

    // -------------------------------------------------------------------------
    // Reading ACF groups
    // -------------------------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    private function databaseGroups(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, post_type, post_title, post_name, post_excerpt, post_content, post_status, post_parent, menu_order
             FROM {$wpdb->posts}
             WHERE post_type IN ('acf-field-group', 'acf-field') AND post_status <> 'trash'
             ORDER BY menu_order ASC, ID ASC",
            ARRAY_A
        );

        $children = [];
        $groups   = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if ($row['post_type'] === 'acf-field') {
                $children[(int) $row['post_parent']][] = $row;
            } else {
                $groups[] = $row;
            }
        }

        $out = [];
        foreach ($groups as $row) {
            $settings = maybe_unserialize($row['post_content']);
            $group    = (is_array($settings) ? $settings : []) + [
                'key'    => $row['post_name'],
                'title'  => $row['post_title'],
                'active' => $row['post_status'] === 'publish',
            ];
            $group['key']    = $row['post_name'];
            $group['title']  = $row['post_title'];
            $group['active'] = $row['post_status'] === 'publish';
            $group['fields'] = $this->databaseFields((int) $row['ID'], $children);
            $out[(string) $row['post_name']] = $group;
        }

        return $out;
    }

    /**
     * @param  array<int, list<array<string, mixed>>> $children
     * @return list<array<string, mixed>>
     */
    private function databaseFields(int $parentId, array $children): array
    {
        $fields = [];
        foreach ($children[$parentId] ?? [] as $row) {
            $settings = maybe_unserialize($row['post_content']);
            $field    = (is_array($settings) ? $settings : []);
            $field['key']   = $row['post_name'];
            $field['label'] = $row['post_title'];
            $field['name']  = $row['post_excerpt'];

            $subs = $this->databaseFields((int) $row['ID'], $children);
            if (($field['type'] ?? '') === 'flexible_content') {
                $layouts = [];
                foreach (is_array($field['layouts'] ?? null) ? $field['layouts'] : [] as $layoutKey => $layout) {
                    if (! is_array($layout)) {
                        continue;
                    }
                    $lk                   = (string) ($layout['key'] ?? $layoutKey);
                    $layout['sub_fields'] = array_values(array_filter($subs, static fn ($s) => ($s['parent_layout'] ?? '') === $lk));
                    $layouts[]            = $layout;
                }
                $field['layouts'] = $layouts;
            } elseif ($subs !== []) {
                $field['sub_fields'] = $subs;
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /** @return array<string, array<string, mixed>> */
    private function jsonGroups(): array
    {
        $dirs = array_unique([
            get_stylesheet_directory() . '/acf-json',
            get_template_directory() . '/acf-json',
        ]);
        /** @var list<string> $dirs */
        $dirs = (array) apply_filters('ctrlfield/acf_json_paths', array_values($dirs));

        $out = [];
        foreach ($dirs as $dir) {
            foreach (glob(rtrim((string) $dir, '/') . '/group_*.json') ?: [] as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data) && is_string($data['key'] ?? null) && isset($data['fields'])) {
                    // ACF JSON keeps flexible layouts keyed by layout key.
                    $data['fields'] = $this->listLayouts($data['fields']);
                    $out[$data['key']] = $data;
                }
            }
        }

        return $out;
    }

    /**
     * @param  mixed $fields
     * @return list<array<string, mixed>>
     */
    private function listLayouts(mixed $fields): array
    {
        $out = [];
        foreach (is_array($fields) ? $fields : [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            if (isset($f['layouts']) && is_array($f['layouts'])) {
                $f['layouts'] = array_values(array_map(fn ($l) => is_array($l) ? ['sub_fields' => $this->listLayouts($l['sub_fields'] ?? [])] + $l : $l, $f['layouts']));
            }
            if (isset($f['sub_fields'])) {
                $f['sub_fields'] = $this->listLayouts($f['sub_fields']);
            }
            $out[] = $f;
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    /** @return array<string, string> ACF group key => CtrlField group key */
    private function groupMap(): array
    {
        $map = get_option(self::GROUP_MAP_OPTION, []);

        return is_array($map) ? $map : [];
    }

    private function uniqueKey(string $base): string
    {
        $taken = static fn (string $k): bool => FieldRegistry::has($k);
        $key   = $base;
        for ($i = 2; $this->repo->get($key) !== null || $taken($key); $i++) {
            $key = substr($base, 0, 60) . '_' . $i;
        }

        return $key;
    }
}
