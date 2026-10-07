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

    /** @var list<array<string, mixed>> groups added before init */
    private static array $pendingGroups = [];

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    public static function getField(string $selector, mixed $postId = false, bool $format = true): mixed
    {
        [$def, $raw] = self::rawField($selector, $postId);

        if ($def === null) {
            return null;
        }

        return $format ? AcfValues::format($raw, $def) : $raw;
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
            $out[$key] = $def !== null && $format ? AcfValues::format($raw, $def) : $raw;
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

        $definition = $def->getDefinition();
        $object     = [
            'ID'            => 0,
            'key'           => $key,
            'label'         => (string) ($definition['label'] ?? ''),
            'name'          => $key,
            'type'          => self::acfType($def->getType()),
            'instructions'  => $def->getInstructions(),
            'required'      => $def->isRequired() ? 1 : 0,
            'default_value' => $def->getDefault(),
            'return_format' => $def->getReturnFormat(),
        ];
        if (method_exists($def, 'getOptions')) {
            $object['choices'] = $def->getOptions();
        }
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

    /** @param array<string, mixed> $acfGroup */
    public static function addLocalFieldGroup(array $acfGroup): bool
    {
        if (function_exists('did_action') && ! did_action('init')) {
            self::$pendingGroups[] = $acfGroup; // translations and rules are ready on init
            return true;
        }

        $key       = AcfConverter::keyFor((string) ($acfGroup['key'] ?? $acfGroup['title'] ?? 'acf_group'));
        $converted = (new AcfConverter())->convertGroup($acfGroup, $key);
        self::$runtimeKeys += $converted['fieldKeys'];

        [$group, $errors] = JsonGroup::normalize($converted['group']);
        if ($errors !== []) {
            SchemaErrors::add('acf_add_local_field_group(' . $key . ')', new \RuntimeException(implode(' ', $errors)));
            return false;
        }

        try {
            return JsonGroup::register($group);
        } catch (\Throwable $e) {
            SchemaErrors::add('acf_add_local_field_group(' . $key . ')', $e);
            return false;
        }
    }

    public static function registerPendingGroups(): void
    {
        $pending             = self::$pendingGroups;
        self::$pendingGroups = [];
        foreach ($pending as $group) {
            self::addLocalFieldGroup($group);
        }
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
        if (str_starts_with($selector, 'field_')) {
            $map = self::$runtimeKeys + (array) get_option(self::FIELD_KEYS_OPTION, []);
            if (isset($map[$selector])) {
                return (string) $map[$selector];
            }
        }
        if (FieldWriter::findField($selector) !== null) {
            return $selector;
        }
        $key = AcfConverter::keyFor($selector);

        return FieldWriter::findField($key) !== null ? $key : null;
    }

    private static function acfType(FieldType $type): string
    {
        $flipped = array_flip(AcfConverter::TYPES);

        return (string) ($flipped[$type->value] ?? $type->value);
    }
}
