<?php

declare(strict_types=1);

namespace CtrlField\Schema;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Conditions\ConditionValidator;
use CtrlField\Fields\Field;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Registry\ContextRegistry;

/**
 * Field groups stored as JSON (created in CtrlField → Field Groups).
 *
 * JSON is data, not code: a group file can only produce the calls listed in
 * TYPES / SETTINGS below, so editing groups from the admin never writes
 * executable PHP. The same call plan builds the FieldGroup and the PHP export,
 * so "Export PHP" always matches what the JSON registers.
 *
 * Format:
 * {
 *   "key": "home_hero", "title": "Home hero", "active": true,
 *   "location": [{"key": "post_type", "operator": "==", "value": "page"}],
 *   "position": "normal", "style": "default", "labelPlacement": "top",
 *   "fields": [{"key": "headline", "type": "text", "label": "Headline", "required": true, ...}]
 * }
 */
final class JsonGroup
{
    /**
     * Field types the editor offers: type => [factory, settings, pro].
     * Settings are applied in this order (multiple before appearance).
     */
    public const TYPES = [
        'text'          => ['text', ['placeholder', 'default'], false],
        'textarea'      => ['textarea', ['placeholder', 'default'], false],
        'number'        => ['number', ['placeholder', 'default'], false],
        'email'         => ['email', ['placeholder', 'default'], false],
        'url'           => ['url', ['placeholder', 'default'], false],
        'password'      => ['password', ['placeholder'], false],
        'wysiwyg'       => ['wysiwyg', ['default'], false],
        'range'         => ['range', ['min', 'max', 'step', 'default'], false],
        'select'        => ['select', ['options', 'default', 'returnFormat'], false],
        'radio'         => ['radio', ['options', 'default', 'returnFormat'], false],
        'checkbox'      => ['checkbox', ['options', 'returnFormat'], false],
        'button_group'  => ['buttonGroup', ['options', 'allowNull', 'default', 'returnFormat'], false],
        'true_false'    => ['trueFalse', ['message'], false],
        'date'          => ['date', ['format', 'returnFormat'], false],
        'datetime'      => ['datetime', ['format', 'returnFormat'], false],
        'time'          => ['time', ['returnFormat'], false],
        'color'         => ['color', ['default'], false],
        'image'         => ['image', ['returnFormat'], false],
        'file'          => ['file', ['returnFormat'], false],
        'link'          => ['link', ['postType', 'taxonomies', 'noAnchors', 'styles', 'returnFormat'], false],
        'oembed'        => ['oembed', [], false],
        'page_link'     => ['pageLink', ['postType', 'multiple'], false],
        'post_object'   => ['postObject', ['postType', 'multiple', 'returnFormat'], false],
        'relationship'  => ['relationship', ['relatedPostType', 'bidirectional', 'minItems', 'maxItems', 'returnFormat'], false],
        'taxonomy_term' => ['taxonomyTerm', ['taxonomy', 'multiple', 'appearance', 'returnFormat'], false],
        'user'          => ['user', ['roles', 'multiple', 'returnFormat'], false],
        'map'           => ['map', [], false],
        'icon'          => ['icon', [], false],
        'code'          => ['code', ['language'], false],
        'group'         => ['object', ['fields'], false],
        'tab'           => ['tab', [], false],
        'accordion'     => ['accordion', ['closed'], false],
        'accordion_end' => ['accordionEnd', [], false],
        'message'       => ['message', ['content'], false],
        'separator'     => ['separator', [], false],
        'repeater'      => ['repeater', ['fields'], true],
        'gallery'       => ['gallery', ['minItems', 'maxItems', 'returnFormat'], true],
        // Layouts are kept and registered, but edited in PHP (no editor UI yet).
        'flexible_content' => ['flexibleContent', ['layouts'], true],
    ];

    /** Settings every field type accepts (besides key / type / label). */
    private const COMMON = ['required', 'instructions', 'width', 'showInRest', 'adminColumn', 'visibleWhen'];

    /** Types that hold no value (layout only): no required / column / REST. */
    private const LAYOUT_TYPES = ['tab', 'message', 'separator', 'accordion', 'accordion_end'];

    /** Setting => kind. Only these reach a FieldDefinition method. */
    private const SETTINGS = [
        'required'        => 'bool',
        'closed'          => 'bool',
        'instructions'    => 'text',
        'width'           => 'width',
        'showInRest'      => 'bool',
        'adminColumn'     => 'bool',
        'visibleWhen'     => 'condition',
        'placeholder'     => 'string',
        'default'         => 'scalar',
        'min'             => 'number',
        'max'             => 'number',
        'step'            => 'number',
        'options'         => 'options',
        'allowNull'       => 'bool',
        'message'         => 'string',
        'content'         => 'text',
        'format'          => 'string',
        'postType'        => 'slugs',
        'relatedPostType' => 'slug',
        'multiple'        => 'bool',
        'returnFormat'    => 'format',
        'bidirectional'   => 'bool',
        'minItems'        => 'count',
        'maxItems'        => 'count',
        'taxonomy'        => 'slug',
        'appearance'      => ['select', 'checkbox', 'radio'],
        'roles'           => 'slugs',
        'taxonomies'      => 'slugs',
        'noAnchors'       => 'bool',
        'styles'          => 'options',
        'language'        => 'slug',
        'fields'          => 'fields',
        'layouts'         => 'layouts',
    ];

    public const OPERATORS          = ['==', '!='];
    public const CONDITION_OPERATORS = ['==', '!=', 'contains', 'empty', 'not_empty', '>', '<'];
    public const POSITIONS          = ['normal', 'side', 'after_title'];
    public const STYLES             = ['default', 'seamless'];
    public const LABEL_PLACEMENTS   = ['top', 'left'];

    // Mixed case is allowed: ACF themes use camelCase names (contentHtml) and
    // templates read them by that exact name. The editor proposes lowercase.
    private const KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,63}$/';
    private const MAX_DEPTH   = 5;
    private const MAX_FIELDS  = 300;

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Clean an untrusted group array (admin form, JSON file) into the canonical
     * format. Unknown properties are dropped.
     *
     * @param  array<mixed> $data
     * @return array{0: array<string, mixed>, 1: list<string>} [group, errors]
     */
    public static function normalize(array $data): array
    {
        $errors = [];

        $key = is_string($data['key'] ?? null) ? trim($data['key']) : '';
        if (! preg_match(self::KEY_PATTERN, $key)) {
            $errors[] = __('Group key: use lowercase letters, numbers and underscores, starting with a letter.', 'ctrlfield');
        }

        $title = self::text($data['title'] ?? '', 200);
        if ($title === '') {
            $errors[] = __('Give the field group a title.', 'ctrlfield');
        }

        $location    = self::normalizeRules($data['location'] ?? null, $errors);
        $locationAny = self::normalizeRules($data['locationAny'] ?? null, $errors);
        if ($location === [] && $locationAny === [] && $errors === []) {
            $errors[] = __('Add at least one location rule, otherwise the group is shown nowhere.', 'ctrlfield');
        }

        $count  = 0;
        $fields = self::normalizeFields($data['fields'] ?? [], 1, $errors, $count);

        $group = [
            'key'            => $key,
            'title'          => $title,
            'active'         => ! array_key_exists('active', $data) || (bool) $data['active'],
            'location'       => $location,
            'locationAny'    => $locationAny,
            'position'       => self::oneOf($data['position'] ?? null, self::POSITIONS),
            'style'          => self::oneOf($data['style'] ?? null, self::STYLES),
            'labelPlacement' => self::oneOf($data['labelPlacement'] ?? null, self::LABEL_PLACEMENTS),
            'fields'         => $fields,
        ];

        if ($errors === []) {
            // Rules that need the built objects (operator vs. field type, …).
            try {
                self::checkBuilt($group);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [$group, array_values(array_unique($errors))];
    }

    /**
     * @param  list<string> $errors
     * @return list<array{key: string, operator: string, value: string}>
     */
    private static function normalizeRules(mixed $raw, array &$errors): array
    {
        $rules = [];
        foreach (is_array($raw) ? $raw : [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $rKey  = is_string($rule['key'] ?? null) ? $rule['key'] : '';
            $op    = in_array($rule['operator'] ?? null, self::OPERATORS, true) ? $rule['operator'] : '==';
            $value = self::text($rule['value'] ?? '', 200);
            if (! ContextRegistry::isValidKey($rKey)) {
                $errors[] = sprintf(__('Unknown location rule "%s".', 'ctrlfield'), $rKey);
                continue;
            }
            if ($value === '') {
                $errors[] = __('Every location rule needs a value.', 'ctrlfield');
                continue;
            }
            $rules[] = ['key' => $rKey, 'operator' => $op, 'value' => $value];
        }

        return $rules;
    }

    /**
     * @param  list<string> $errors
     * @return list<array<string, mixed>>
     */
    private static function normalizeFields(mixed $raw, int $depth, array &$errors, int &$count): array
    {
        if (! is_array($raw)) {
            return [];
        }
        if ($depth > self::MAX_DEPTH) {
            $errors[] = sprintf(__('Sub-fields can be nested at most %d levels deep.', 'ctrlfield'), self::MAX_DEPTH);
            return [];
        }

        $fields = [];
        $seen   = [];

        foreach ($raw as $f) {
            if (! is_array($f)) {
                continue;
            }
            if (++$count > self::MAX_FIELDS) {
                $errors[] = sprintf(__('A field group can have at most %d fields.', 'ctrlfield'), self::MAX_FIELDS);
                break;
            }

            $type  = is_string($f['type'] ?? null) ? $f['type'] : '';
            $key   = is_string($f['key'] ?? null) ? trim($f['key']) : '';
            $label = self::text($f['label'] ?? '', 200);
            $name  = $label !== '' ? $label : $key;

            if (! isset(self::TYPES[$type])) {
                $errors[] = sprintf(__('Field "%1$s": unknown type "%2$s".', 'ctrlfield'), $name, $type);
                continue;
            }
            if (! preg_match(self::KEY_PATTERN, $key)) {
                $errors[] = sprintf(__('Field "%s": the key must use lowercase letters, numbers and underscores, starting with a letter.', 'ctrlfield'), $name);
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = sprintf(__('Two fields use the key "%s". Keys must be unique.', 'ctrlfield'), $key);
                continue;
            }
            $seen[$key] = true;

            $field = ['key' => $key, 'type' => $type, 'label' => $label];

            foreach (self::settingsFor($type) as $setting) {
                if (! array_key_exists($setting, $f)) {
                    continue;
                }
                if ($setting === 'fields') {
                    $field['fields'] = self::normalizeFields($f['fields'], $depth + 1, $errors, $count);
                    continue;
                }
                if ($setting === 'layouts') {
                    $field['layouts'] = self::normalizeLayouts($f['layouts'], $depth, $errors, $count);
                    continue;
                }
                // A WYSIWYG default is HTML: keep safe markup instead of plain text.
                $value = $setting === 'default' && $type === 'wysiwyg'
                    ? (is_scalar($f['default']) && ($html = mb_substr(wp_kses_post((string) $f['default']), 0, 5000)) !== '' ? $html : null)
                    : self::cleanSetting($setting, $f[$setting]);
                if ($value !== null) {
                    $field[$setting] = $value;
                }
            }

            $fields[] = $field;
        }

        // visibleWhen may only point at a sibling field.
        foreach ($fields as $i => $field) {
            if (isset($field['visibleWhen']) && ! isset($seen[$field['visibleWhen']['field']])) {
                unset($fields[$i]['visibleWhen']);
            }
        }

        return $fields;
    }

    /**
     * @param  list<string> $errors
     * @return list<array{key: string, label: string, fields: list<array<string, mixed>>}>
     */
    private static function normalizeLayouts(mixed $raw, int $depth, array &$errors, int &$count): array
    {
        $layouts = [];
        $seen    = [];
        foreach (is_array($raw) ? $raw : [] as $l) {
            $key = is_array($l) && is_string($l['key'] ?? null) ? trim($l['key']) : '';
            if (! preg_match(self::KEY_PATTERN, $key) || isset($seen[$key])) {
                $errors[] = sprintf(__('Layout "%s": the key must be unique and use lowercase letters, numbers and underscores.', 'ctrlfield'), $key);
                continue;
            }
            $seen[$key] = true;
            $layouts[]  = [
                'key'    => $key,
                'label'  => self::text($l['label'] ?? '', 200),
                'fields' => self::normalizeFields($l['fields'] ?? [], $depth + 1, $errors, $count),
            ];
        }

        return $layouts;
    }

    /** @return list<string> */
    public static function settingsFor(string $type): array
    {
        $common = in_array($type, self::LAYOUT_TYPES, true) ? [] : self::COMMON;

        return [...$common, ...(self::TYPES[$type][1] ?? [])];
    }

    private static function cleanSetting(string $setting, mixed $v): mixed
    {
        $kind = self::SETTINGS[$setting];

        if (is_array($kind)) {
            return in_array($v, $kind, true) ? $v : null;
        }

        return match ($kind) {
            'bool'   => (bool) $v ?: null,
            'string' => ($s = self::text($v, 500)) !== '' ? $s : null,
            'text'   => ($s = self::text($v, 5000, true)) !== '' ? $s : null,
            'scalar' => is_scalar($v) && ($s = self::text((string) $v, 500)) !== '' ? $s : null,
            'number' => is_numeric($v) ? 0 + $v : null,
            'count'  => is_numeric($v) && (int) $v >= 0 ? (int) $v : null,
            'width'  => in_array((int) $v, [25, 50, 75], true) ? (int) $v : null,
            'slug'   => is_string($v) && ($s = sanitize_key($v)) !== '' ? $s : null,
            'slugs'  => ($l = self::slugs($v)) !== [] ? $l : null,
            'options'   => ($o = self::options($v)) !== [] ? $o : null,
            'condition' => self::condition($v),
            // id, object, url, array, value, label, DateTime, timestamp — or a PHP date format.
            'format'    => is_string($v) && preg_match('/^[A-Za-z0-9 :\/.,\\\\_-]{1,40}$/', $v) ? $v : null,
            default  => null,
        };
    }

    /** @return list<string> */
    private static function slugs(mixed $v): array
    {
        $list = is_string($v) ? [$v] : (is_array($v) ? $v : []);

        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => is_string($s) ? sanitize_key($s) : '',
            $list
        ))));
    }

    /** @return list<array{0: string, 1: string}> [value, label] pairs, kept in order */
    private static function options(mixed $v): array
    {
        $out  = [];
        $seen = [];
        foreach (is_array($v) ? array_slice($v, 0, 500) : [] as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $value = self::text($pair[0] ?? '', 200);
            $label = self::text($pair[1] ?? '', 200);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[]        = [$value, $label !== '' ? $label : $value];
        }

        return $out;
    }

    /** @return array{field: string, operator: string, value: string}|null */
    private static function condition(mixed $v): ?array
    {
        if (! is_array($v) || ! is_string($v['field'] ?? null) || ! preg_match(self::KEY_PATTERN, $v['field'])) {
            return null;
        }
        $op = in_array($v['operator'] ?? null, self::CONDITION_OPERATORS, true) ? $v['operator'] : '==';

        return ['field' => $v['field'], 'operator' => $op, 'value' => self::text($v['value'] ?? '', 200)];
    }

    private static function text(mixed $v, int $max, bool $multiline = false): string
    {
        if (! is_scalar($v)) {
            return '';
        }
        $s = $multiline ? sanitize_textarea_field((string) $v) : sanitize_text_field((string) $v);

        return mb_substr(trim($s), 0, $max);
    }

    /** @param list<string> $allowed */
    private static function oneOf(mixed $v, array $allowed): string
    {
        return in_array($v, $allowed, true) ? $v : $allowed[0];
    }

    /** @param array<string, mixed> $group */
    private static function checkBuilt(array $group): void
    {
        $built = self::build($group);
        $map   = [];
        foreach ($built->getFields() as $field) {
            $map[$field->getKey()] = $field;
        }
        foreach ($built->getFields() as $field) {
            ConditionValidator::validate($field, $map);
        }
    }

    // -------------------------------------------------------------------------
    // Build / export
    // -------------------------------------------------------------------------

    /**
     * Build the FieldGroup from a normalized group (not registered).
     *
     * @param array<string, mixed> $group
     */
    public static function build(array $group): FieldGroup
    {
        $fg = FieldGroup::make((string) $group['key'])->title((string) $group['title']);

        foreach (self::groupCalls($group) as [$method, $args]) {
            $fg->{$method}(...$args);
        }

        return $fg->fields(self::buildFields($group['fields'] ?? []));
    }

    /**
     * Build and register; returns false when the key is taken (code wins).
     *
     * @param array<string, mixed> $group
     */
    public static function register(array $group): bool
    {
        if (\CtrlField\Registry\FieldRegistry::has((string) $group['key'])) {
            return false;
        }
        self::build($group)->register();

        return true;
    }

    /**
     * @param  list<array<string, mixed>> $fields
     * @return list<FieldDefinition>
     */
    private static function buildFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $factory = self::TYPES[$f['type']][0];
            /** @var FieldDefinition $def */
            $def = Field::{$factory}($f['key']);
            foreach (self::fieldCalls($f) as [$method, $args]) {
                if ($method === 'fields') {
                    $args = [self::buildFields($args[0])];
                } elseif ($method === 'layouts') {
                    $args = [self::buildLayouts($args[0])];
                }
                $def->{$method}(...$args);
            }
            $out[] = $def;
        }

        return $out;
    }

    /**
     * FlexLayout lives in Pro; flexible content cannot be built without it
     * (Field::flexibleContent() already throws a clear license error).
     *
     * @param  list<array<string, mixed>> $layouts
     * @return list<object>
     */
    private static function buildLayouts(array $layouts): array
    {
        $class = '\\CtrlField\\Pro\\Fields\\FlexLayout';
        $out   = [];
        foreach ($layouts as $l) {
            $layout = $class::make($l['key']);
            if ($l['label'] !== '') {
                $layout->label($l['label']);
            }
            $out[] = $layout->fields(self::buildFields($l['fields']));
        }

        return $out;
    }

    /**
     * The FieldGroup calls besides make / title / fields.
     *
     * @param  array<string, mixed> $group
     * @return list<array{0: string, 1: list<mixed>}>
     */
    private static function groupCalls(array $group): array
    {
        $calls = [];
        foreach ($group['location'] ?? [] as $rule) {
            $value   = ctype_digit($rule['value']) ? (int) $rule['value'] : $rule['value'];
            $calls[] = ['where', [$rule['key'], $rule['operator'], $value]];
        }
        if (($group['locationAny'] ?? []) !== []) {
            $any = [];
            foreach ($group['locationAny'] as $rule) {
                $any[] = [$rule['key'], $rule['operator'], ctype_digit($rule['value']) ? (int) $rule['value'] : $rule['value']];
            }
            $calls[] = ['whereAny', [$any]];
        }
        if (($group['position'] ?? 'normal') !== 'normal') {
            $calls[] = ['position', [$group['position']]];
        }
        if (($group['style'] ?? 'default') !== 'default') {
            $calls[] = ['style', [$group['style']]];
        }
        if (($group['labelPlacement'] ?? 'top') !== 'top') {
            $calls[] = ['labelPlacement', [$group['labelPlacement']]];
        }

        return $calls;
    }

    /**
     * Method calls for one normalized field, in a fixed order.
     *
     * @param  array<string, mixed> $f
     * @return list<array{0: string, 1: list<mixed>}>
     */
    private static function fieldCalls(array $f): array
    {
        $calls = [];
        if (($f['label'] ?? '') !== '') {
            $calls[] = ['label', [$f['label']]];
        }

        foreach (self::settingsFor($f['type']) as $setting) {
            if (! array_key_exists($setting, $f)) {
                continue;
            }
            $v = $f[$setting];

            switch ($setting) {
                case 'required':
                case 'showInRest':
                case 'multiple':
                case 'bidirectional':
                case 'allowNull':
                    $calls[] = [$setting, []];
                    break;
                case 'closed':
                    $calls[] = ['open', [false]];
                    break;
                case 'noAnchors':
                    $calls[] = ['anchors', [false]];
                    break;
                case 'adminColumn':
                    // A column needs the index row (sorting / filtering).
                    $calls[] = ['setIndex', []];
                    $calls[] = ['adminColumn', []];
                    break;
                case 'visibleWhen':
                    $calls[] = ['visibleWhen', [$v['field'], $v['operator'], $v['value']]];
                    break;
                case 'options':
                case 'styles':
                    $opts = [];
                    foreach ($v as [$value, $label]) {
                        $opts[$value] = $label;
                    }
                    $calls[] = [$setting, [$opts]];
                    break;
                case 'default':
                    $calls[] = ['default', [$f['type'] === 'number' || $f['type'] === 'range' ? (is_numeric($v) ? 0 + $v : $v) : $v]];
                    break;
                case 'step':
                case 'min':
                case 'max':
                    $calls[] = [$setting, [(float) $v]];
                    break;
                case 'postType':
                    $calls[] = ['postType', [count($v) === 1 ? $v[0] : $v]];
                    break;
                case 'relatedPostType':
                    $calls[] = ['postType', [$v]];
                    break;
                case 'fields':
                case 'layouts':
                    $calls[] = [$setting, [$v]];
                    break;
                default:
                    $calls[] = [$setting, [$v]];
            }
        }

        return $calls;
    }

    /**
     * The group as a PHP schema file, for developers who move it into code.
     *
     * @param array<string, mixed> $group
     */
    public static function toPhp(array $group): string
    {
        $i   = '    ';
        $flex = str_contains((string) wp_json_encode($group['fields'] ?? []), '"layouts"');
        $out  = "<?php\n\ndeclare(strict_types=1);\n\nuse CtrlField\\Builder\\FieldGroup;\nuse CtrlField\\Fields\\Field;\n"
            . ($flex ? "use CtrlField\\Pro\\Fields\\FlexLayout;\n" : '') . "\n"
            . 'FieldGroup::make(' . self::lit($group['key']) . ")\n"
            . "{$i}->title(" . self::lit($group['title']) . ")\n";

        foreach (self::groupCalls($group) as [$method, $args]) {
            $out .= "{$i}->{$method}(" . implode(', ', array_map(self::lit(...), $args)) . ")\n";
        }

        $out .= "{$i}->fields([\n" . self::phpFields($group['fields'] ?? [], 2) . "{$i}])\n{$i}->register();\n";

        return $out;
    }

    /** @param list<array<string, mixed>> $fields */
    private static function phpFields(array $fields, int $level): string
    {
        $pad = str_repeat('    ', $level);
        $out = '';
        foreach ($fields as $f) {
            $out .= $pad . 'Field::' . self::TYPES[$f['type']][0] . '(' . self::lit($f['key']) . ')';
            foreach (self::fieldCalls($f) as [$method, $args]) {
                if ($method === 'fields') {
                    $out .= "\n{$pad}    ->fields([\n" . self::phpFields($args[0], $level + 2) . "{$pad}    ])";
                    continue;
                }
                if ($method === 'layouts') {
                    $out .= "\n{$pad}    ->layouts([\n";
                    foreach ($args[0] as $l) {
                        $out .= "{$pad}        FlexLayout::make(" . self::lit($l['key']) . ')'
                            . ($l['label'] !== '' ? '->label(' . self::lit($l['label']) . ')' : '')
                            . "->fields([\n" . self::phpFields($l['fields'], $level + 3) . "{$pad}        ]),\n";
                    }
                    $out .= "{$pad}    ])";
                    continue;
                }
                $out .= "\n{$pad}    ->{$method}(" . implode(', ', array_map(self::lit(...), $args)) . ')';
            }
            $out .= ",\n";
        }

        return $out;
    }

    /** A PHP literal for a scalar or (nested) array. */
    private static function lit(mixed $v): string
    {
        if (is_array($v)) {
            $isList = array_is_list($v);
            $parts  = [];
            foreach ($v as $k => $item) {
                $parts[] = ($isList ? '' : self::lit($k) . ' => ') . self::lit($item);
            }
            return '[' . implode(', ', $parts) . ']';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return floor($v) === $v && abs($v) < 1e15 ? number_format($v, 0, '.', '') . '.0' : (string) $v;
        }

        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $v) . "'";
    }
}
