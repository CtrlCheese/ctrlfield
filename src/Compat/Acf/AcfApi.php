<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Builder\OptionsPage;
use CtrlField\Data\FieldDataService;
use CtrlField\Data\FieldWriter;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\JsonGroup;
use CtrlField\Schema\SchemaErrors;

/**
 * The logic behind the ACF function names (see functions.php), so themes and
 * plugins written for ACF read and write CtrlField data unchanged.
 */
final class AcfApi
{
    public const FIELD_KEYS_OPTION = 'ctrlfield_acf_field_keys';

    /** @var array<string, string> ACF field keys (field_…) registered this request */
    private static array $runtimeKeys = [];

    /** @var array<string, array<string, mixed>> ACF local groups by ACF key */
    private static array $localGroups = [];

    /** @var array<string, true> CtrlField keys registered from local groups */
    private static array $registered = [];

    private static bool $flushed = false;

    /** acf/init has finished: theme objects that filters rely on exist. */
    private static bool $acfInitDone = false;

    public static function markAcfInitDone(): void
    {
        self::$acfInitDone = true;
    }

    /** The real ACF plugin, not CtrlField's stand-in class. */
    public static function isRealAcf(): bool
    {
        return class_exists('ACF', false) && ! defined('ACF::CTRLFIELD_SHIM');
    }

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    public static function getField(string $selector, mixed $postId = false, bool $format = true): mixed
    {
        [$def, $raw] = self::rawField($selector, $postId);

        if ($def === null) {
            return null;
        }

        return $format ? AcfValues::format($raw, $def, self::acfPostId($postId, $def->getKey())) : $raw;
    }

    /**
     * @return array{0: FieldDefinition|null, 1: mixed} the field and its stored value
     */
    public static function rawField(string $selector, mixed $postId = false): array
    {
        $key = self::resolveKey($selector);
        $def = $key !== null ? FieldWriter::findField($key) : null;

        if ($key === null || $def === null) {
            return [null, null];
        }

        [$type, $id] = self::target($postId, $key);
        if ($id === 0 || $id === '') {
            return [$def, null];
        }

        if ($def->getType() === FieldType::RELATIONSHIP) {
            $ids = $type === 'post' && function_exists('ctrlfield_get_relationship_ids')
                ? ctrlfield_get_relationship_ids($key, (int) $id)
                : [];
            return [$def, $ids];
        }

        return [$def, FieldDataService::getInstance()->getAll($id, $type)[$key] ?? null];
    }

    /** @return array<string, mixed>|false */
    public static function getFields(mixed $postId = false, bool $format = true): array|false
    {
        [$type, $id] = self::target($postId, null);
        if ($id === 0 || $id === '') {
            return false;
        }

        $out = [];
        foreach (FieldDataService::getInstance()->getAll($id, $type) as $key => $raw) {
            $def       = FieldWriter::findField((string) $key);
            $out[$key] = $def !== null && $format ? AcfValues::format($raw, $def, self::acfPostId($postId, null)) : $raw;
        }

        return $out === [] ? false : $out;
    }

    /** @return array<string, mixed>|false */
    public static function getFieldObject(string $selector, mixed $postId = false, bool $format = true, bool $loadValue = true): array|false
    {
        $key = self::resolveKey($selector);
        $def = $key !== null ? FieldWriter::findField($key) : null;
        if ($def === null) {
            return false;
        }

        $object = self::fieldArray($def);
        unset($object['_ctrlfield']);
        if ($loadValue) {
            $object['value'] = self::getField($key, $postId, $format);
        }

        return $object;
    }

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    public static function updateField(string $selector, mixed $value, mixed $postId = false): bool
    {
        $key = self::resolveKey($selector);
        $def = $key !== null ? FieldWriter::findField($key) : null;
        if ($key === null || $def === null) {
            return false;
        }

        [$type, $id] = self::target($postId, $key);
        if ($id === 0 || $id === '') {
            return false;
        }

        try {
            return FieldWriter::write($type, $id, [$key => AcfValues::toStorage($value, $def)]) === [];
        } catch (\Throwable) {
            return false;
        }
    }

    public static function deleteField(string $selector, mixed $postId = false): bool
    {
        $key = self::resolveKey($selector);
        if ($key === null || FieldWriter::findField($key) === null) {
            return false;
        }
        [$type, $id] = self::target($postId, $key);
        if ($id === 0 || $id === '') {
            return false;
        }
        FieldWriter::delete($type, $id, $key);

        return true;
    }

    /** Append a row to a repeater or flexible content field; returns the new row count. */
    public static function addRow(string $selector, mixed $row, mixed $postId = false): int|false
    {
        [$def, $raw] = self::rawField($selector, $postId);
        if ($def === null || ! in_array($def->getType(), [FieldType::REPEATER, FieldType::FLEXIBLE_CONTENT], true)) {
            return false;
        }
        $rows   = AcfValues::toStorage(is_array($raw) ? $raw : [], $def);
        $rows[] = AcfValues::toStorage([$row], $def)[0] ?? [];

        return self::updateRaw($selector, $rows, $postId, $def) ? count($rows) : false;
    }

    private static function updateRaw(string $selector, mixed $storage, mixed $postId, FieldDefinition $def): bool
    {
        [$type, $id] = self::target($postId, $def->getKey());
        try {
            return FieldWriter::write($type, $id, [$def->getKey() => $storage]) === [];
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Registration (acf_add_local_field_group, acf_add_options_page)
    // -------------------------------------------------------------------------

    /**
     * acf_add_local_field_group(). Groups are collected and registered together
     * (flushLocalGroups) so acf_add_local_field() can still add fields to them,
     * as Flynt's options do; a group changed after that is registered again.
     *
     * @param array<string, mixed> $acfGroup
     */
    public static function addLocalFieldGroup(array $acfGroup): bool
    {
        $acfKey = (string) ($acfGroup['key'] ?? ('group_' . AcfConverter::keyFor((string) ($acfGroup['title'] ?? 'acf_group'))));
        $acfGroup['key']             = $acfKey;
        $acfGroup['fields']          = is_array($acfGroup['fields'] ?? null) ? $acfGroup['fields'] : [];
        self::$localGroups[$acfKey]  = $acfGroup;

        if (self::$flushed) {
            self::registerLocalGroup($acfKey);
        }

        return true;
    }

    /**
     * acf_add_local_field(): a field for the local group named by 'parent'.
     *
     * @param array<string, mixed> $field
     */
    public static function addLocalField(array $field): bool
    {
        $parent = (string) ($field['parent'] ?? '');
        if ($parent === '' || ! isset(self::$localGroups[$parent])) {
            return false;
        }
        unset($field['parent']);
        self::$localGroups[$parent]['fields'][] = $field;

        if (self::$flushed) {
            self::registerLocalGroup($parent);
        }

        return true;
    }

    /** Register every collected group; later additions register immediately. */
    public static function flushLocalGroups(): void
    {
        if (self::$flushed) {
            return;
        }
        self::$flushed = true;
        foreach (array_keys(self::$localGroups) as $acfKey) {
            self::registerLocalGroup($acfKey);
        }
    }

    /** For tests. */
    public static function resetLocalGroups(): void
    {
        self::$localGroups = [];
        self::$registered  = [];
        self::$flushed     = false;
        self::$acfInitDone = false;
        self::$runtimeKeys = [];
    }

    private static function registerLocalGroup(string $acfKey): void
    {
        $acfGroup = self::$localGroups[$acfKey];
        $key      = AcfConverter::keyFor($acfKey);
        $label    = 'acf_add_local_field_group(' . $acfKey . ')';

        $acfGroup['fields'] = self::loadFields($acfGroup['fields']);
        $converted          = (new AcfConverter())->convertGroup($acfGroup, $key);
        self::$runtimeKeys  = $converted['fieldKeys'] + self::$runtimeKeys;

        [$group, $errors] = JsonGroup::normalize($converted['group']);
        if ($errors !== []) {
            SchemaErrors::add($label, new \RuntimeException(implode(' ', $errors)));
            return;
        }

        // Re-registering our own earlier version is fine; a group from code wins.
        if (FieldRegistry::has($key) && ! isset(self::$registered[$key])) {
            return;
        }
        FieldRegistry::remove($key);

        try {
            JsonGroup::register($group);
            self::$registered[$key] = true;
        } catch (\Throwable $e) {
            SchemaErrors::add($label, $e);
        }
    }

    /**
     * acf/load_field (with /type=, /name=, /key=) lets themes adjust a field
     * (choices, labels, placeholders) or drop it (false).
     *
     * @param  array<mixed> $fields
     * @return list<array<string, mixed>>
     */
    private static function loadFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            // acf/prepare_field is not applied: ACF runs it while rendering, with the value loaded.
            foreach (['acf/load_field'] as $hook) {
                foreach (['', '/type=' . ($field['type'] ?? ''), '/name=' . ($field['name'] ?? ''), '/key=' . ($field['key'] ?? '')] as $variation) {
                    try {
                        $filtered = apply_filters($hook . $variation, $field);
                    } catch (\Throwable $e) {
                        // A theme filter that cannot run yet must not take the site down.
                        error_log("CtrlField: {$hook}{$variation} failed and was skipped: {$e->getMessage()}");
                        continue;
                    }
                    if (! is_array($filtered)) {
                        continue 3; // hidden
                    }
                    $field = $filtered;
                }
            }
            if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
                $field['sub_fields'] = self::loadFields($field['sub_fields']);
            }
            if (isset($field['layouts']) && is_array($field['layouts'])) {
                foreach ($field['layouts'] as $i => $layout) {
                    if (is_array($layout) && is_array($layout['sub_fields'] ?? null)) {
                        $field['layouts'][$i]['sub_fields'] = self::loadFields($layout['sub_fields']);
                    }
                }
            }
            $out[] = $field;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|string $args
     * @return array<string, mixed>
     */
    public static function addOptionsPage(array|string $args, ?string $defaultParent = null): array
    {
        $args = is_string($args) ? ['page_title' => $args] : $args;

        $pageTitle = (string) ($args['page_title'] ?? __('Options', 'ctrlfield'));
        $menuTitle = (string) ($args['menu_title'] ?? $pageTitle);
        $slug      = (string) ($args['menu_slug'] ?? ('acf-options-' . sanitize_title($menuTitle)));
        $parent    = (string) ($args['parent_slug'] ?? ($defaultParent ?? ''));

        if (! OptionsPage::has($slug)) {
            $page = OptionsPage::make($slug)
                ->title($pageTitle)
                ->menuSlug($slug)
                ->capability((string) ($args['capability'] ?? 'edit_posts'))
                ->parent($parent);
            if (is_string($args['icon_url'] ?? null) && $args['icon_url'] !== '') {
                $page->icon($args['icon_url']);
            }
            if (isset($args['position']) && is_numeric($args['position'])) {
                $page->position((int) $args['position']);
            }
            $page->register();
        }

        return ['page_title' => $pageTitle, 'menu_title' => $menuTitle, 'menu_slug' => $slug, 'parent_slug' => $parent];
    }

    /** The first page added with acf_add_options_page(): default parent of sub pages. */
    public static function firstOptionsPage(): ?string
    {
        $first = array_key_first(OptionsPage::all());

        return $first === null ? null : (string) $first;
    }

    // -------------------------------------------------------------------------
    // Targets and keys
    // -------------------------------------------------------------------------

    /**
     * ACF's $post_id argument → [entity type, id].
     *
     * @return array{0: string, 1: int|string}
     */
    public static function target(mixed $postId, ?string $fieldKey): array
    {
        if ($postId instanceof \WP_Post) {
            return ['post', $postId->ID];
        }
        if ($postId instanceof \WP_Term) {
            return ['term', $postId->term_id];
        }
        if ($postId instanceof \WP_User) {
            return ['user', $postId->ID];
        }
        if ($postId instanceof \WP_Comment) {
            return ['comment', (int) $postId->comment_ID];
        }
        if (is_numeric($postId) && (int) $postId > 0) {
            return ['post', (int) $postId];
        }

        if (is_string($postId) && $postId !== '') {
            if ($postId === 'option' || $postId === 'options' || str_starts_with($postId, 'options_')) {
                return ['options', self::optionsPageFor($fieldKey)];
            }
            if (preg_match('/^([a-z0-9_-]+)_(\d+)$/', $postId, $m)) {
                $id = (int) $m[2];
                return match (true) {
                    $m[1] === 'user'                                  => ['user', $id],
                    $m[1] === 'comment'                               => ['comment', $id],
                    $m[1] === 'term' || taxonomy_exists($m[1])        => ['term', $id],
                    default                                           => ['post', 0],
                };
            }
        }

        // Current object: the post in the loop, else a term / author archive.
        $id = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($id > 0) {
            return ['post', $id];
        }
        $object = function_exists('get_queried_object') ? get_queried_object() : null;
        if ($object instanceof \WP_Term || $object instanceof \WP_User || $object instanceof \WP_Post) {
            return self::target($object, $fieldKey);
        }

        return ['post', 0];
    }

    /** "option" means every options page in ACF; CtrlField stores each page apart. */
    private static function optionsPageFor(?string $fieldKey): string
    {
        if ($fieldKey !== null) {
            foreach (FieldRegistry::all() as $group) {
                $pages = [];
                foreach ($group->getAndConditions() as $c) {
                    if ($c['key'] === 'options_page' && $c['operator'] === '==') {
                        $pages[] = (string) $c['value'];
                    }
                }
                foreach ($group->getOrGroups() as $or) {
                    foreach ($or as $c) {
                        if ($c['key'] === 'options_page' && $c['operator'] === '==') {
                            $pages[] = (string) $c['value'];
                        }
                    }
                }
                if ($pages === []) {
                    continue;
                }
                foreach ($group->getFields() as $field) {
                    if ($field->getKey() === $fieldKey) {
                        return $pages[0];
                    }
                }
            }
        }

        return self::firstOptionsPage() ?? '';
    }

    /** Field name, renamed ACF name, or ACF field key (field_…) → CtrlField key. */
    public static function resolveKey(string $selector): ?string
    {
        if (self::$acfInitDone) {
            self::flushLocalGroups(); // a template may read before init:99
        }
        if (str_starts_with($selector, 'field_')) {
            $map = self::$runtimeKeys + (array) get_option(self::FIELD_KEYS_OPTION, []);
            if (isset($map[$selector])) {
                return (string) $map[$selector];
            }
        }
        if (FieldWriter::findField($selector) !== null) {
            return $selector;
        }
        foreach ([AcfConverter::keyFor($selector), strtolower(AcfConverter::keyFor($selector))] as $key) {
            if (FieldWriter::findField($key) !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * A field in ACF's array shape, as filters and Timber expect it. The
     * CtrlField definition rides along in _ctrlfield for AcfFieldType.
     *
     * @return array<string, mixed>
     */
    public static function fieldArray(FieldDefinition $def): array
    {
        $key        = $def->getKey();
        $type       = self::acfType($def->getType());
        $definition = $def->getDefinition();
        $acfKey     = array_search($key, self::$runtimeKeys + (array) get_option(self::FIELD_KEYS_OPTION, []), true);
        $defaults   = [
            'image' => 'array', 'file' => 'array', 'gallery' => 'array', 'link' => 'array', 'user' => 'array',
            'post_object' => 'object', 'relationship' => 'object', 'taxonomy' => 'id',
            'select' => 'value', 'checkbox' => 'value', 'radio' => 'value', 'button_group' => 'value',
            'date_picker' => 'd/m/Y', 'date_time_picker' => 'd/m/Y g:i a', 'time_picker' => 'g:i a',
        ];

        $field = [
            'ID'            => 0,
            'key'           => is_string($acfKey) ? $acfKey : 'field_' . $key,
            'name'          => $key,
            '_name'         => $key,
            'label'         => (string) ($definition['label'] ?? ''),
            'type'          => $type,
            'instructions'  => $def->getInstructions(),
            'required'      => $def->isRequired() ? 1 : 0,
            'default_value' => $def->getDefault(),
            'return_format' => $def->getReturnFormat() !== '' ? $def->getReturnFormat() : ($defaults[$type] ?? ''),
            '_ctrlfield'    => $def,
        ];
        if (method_exists($def, 'getOptions')) {
            $field['choices'] = $def->getOptions();
        }

        return $field;
    }

    /**
     * ctrlfield/prepare_field → acf/prepare_field (and /type=, /name=, /key=).
     * Themes adjust label / instructions or hide the field (false).
     *
     * @param  array{label: string, instructions: string, hidden: bool} $ui
     * @return array{label: string, instructions: string, hidden: bool}
     */
    public static function prepareField(array $ui, FieldDefinition $def, mixed $value): array
    {
        $field = array_merge(self::fieldArray($def), [
            'label' => $ui['label'], 'instructions' => $ui['instructions'], 'value' => $value, 'prefix' => 'acf',
        ]);
        foreach (['', '/type=' . $field['type'], '/name=' . $field['name'], '/key=' . $field['key']] as $variation) {
            try {
                $filtered = apply_filters('acf/prepare_field' . $variation, $field);
            } catch (\Throwable $e) {
                error_log("CtrlField: acf/prepare_field{$variation} failed and was skipped: {$e->getMessage()}");
                continue;
            }
            if (! is_array($filtered)) {
                return ['label' => '', 'instructions' => '', 'hidden' => true];
            }
            $field = $filtered;
        }

        return ['label' => (string) ($field['label'] ?? ''), 'instructions' => (string) ($field['instructions'] ?? ''), 'hidden' => false];
    }

    /** ctrlfield/flex_layout_title → acf/fields/flexible_content/layout_title. */
    public static function layoutTitle(string $title, FieldDefinition $field, object $layout): string
    {
        $key       = method_exists($layout, 'getKey') ? (string) $layout->getKey() : '';
        $acfLayout = [
            'key'     => 'layout_' . $key,
            'name'    => $key,
            'label'   => method_exists($layout, 'getLabel') ? (string) $layout->getLabel() : $key,
            'display' => 'block',
        ];
        try {
            return (string) apply_filters('acf/fields/flexible_content/layout_title', $title, self::fieldArray($field), $acfLayout, 0);
        } catch (\Throwable $e) {
            error_log("CtrlField: acf/fields/flexible_content/layout_title failed and was skipped: {$e->getMessage()}");
            return $title;
        }
    }

    /** ACF's form of the object an id refers to: 12, 'term_5', 'user_1', 'option'. */
    private static function acfPostId(mixed $postId, ?string $fieldKey): int|string
    {
        [$type, $id] = self::target($postId, $fieldKey);

        return match ($type) {
            'post'    => (int) $id,
            'options' => 'option',
            default   => $type . '_' . $id,
        };
    }

    private static function acfType(FieldType $type): string
    {
        $flipped = array_flip(AcfConverter::TYPES);

        return (string) ($flipped[$type->value] ?? $type->value);
    }
}
