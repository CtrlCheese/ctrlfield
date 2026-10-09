<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

/**
 * Converts ACF field groups (acf-json / acf_add_local_field_group() arrays)
 * into CtrlField's JSON group format (see JsonGroup).
 *
 * Nothing ACF does is executed: the arrays are read as data. What CtrlField
 * cannot represent is dropped with a warning, never guessed.
 */
final class AcfConverter
{
    /** ACF type => CtrlField type. */
    public const TYPES = [
        'text' => 'text', 'textarea' => 'textarea', 'number' => 'number', 'range' => 'range',
        'email' => 'email', 'url' => 'url', 'password' => 'password',
        'image' => 'image', 'file' => 'file', 'wysiwyg' => 'wysiwyg', 'oembed' => 'oembed', 'gallery' => 'gallery',
        'select' => 'select', 'checkbox' => 'checkbox', 'radio' => 'radio', 'button_group' => 'button_group',
        'true_false' => 'true_false',
        'link' => 'link', 'post_object' => 'post_object', 'page_link' => 'page_link', 'relationship' => 'relationship',
        'taxonomy' => 'taxonomy_term', 'user' => 'user',
        'google_map' => 'map', 'date_picker' => 'date', 'date_time_picker' => 'datetime', 'time_picker' => 'time',
        'color_picker' => 'color', 'icon_picker' => 'icon',
        'message' => 'message', 'tab' => 'tab', 'accordion' => 'accordion',
        'group' => 'group', 'repeater' => 'repeater', 'flexible_content' => 'flexible_content',
    ];

    /** ACF location param => CtrlField rule key (null: needs special handling). */
    private const PARAMS = [
        'post_type' => 'post_type', 'page_template' => 'page_template', 'post_template' => 'post_template',
        'page_type' => 'page_type', 'page_parent' => 'post_parent', 'page' => 'post', 'post' => 'post',
        'post_status' => 'post_status', 'post_format' => 'post_format',
        'post_category' => 'post_term', 'post_taxonomy' => 'post_term',
        'taxonomy' => 'taxonomy', 'options_page' => 'options_page',
        'user_role' => 'user_role', 'current_user_role' => 'current_user_role',
        'user_form' => null, 'comment' => null, 'nav_menu_item' => null,
    ];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, string> ACF field key (field_…) => CtrlField key, for conditions and update_field() */
    private array $fieldKeys = [];

    /**
     * @param  array<string, mixed> $acf   ACF group
     * @param  string               $key   CtrlField group key to use
     * @return array{group: array<string, mixed>, warnings: list<string>, fieldKeys: array<string, string>, names: array<string, string>}
     *         names: renamed fields, ACF name => CtrlField key
     */
    public function convertGroup(array $acf, string $key): array
    {
        $this->warnings  = [];
        $this->fieldKeys = [];
        $names           = [];

        $fields = $this->convertFields(is_array($acf['fields'] ?? null) ? $acf['fields'] : [], $names);
        [$location, $locationAny] = $this->convertLocation(is_array($acf['location'] ?? null) ? $acf['location'] : []);

        $position = (string) ($acf['position'] ?? 'normal');

        $group = [
            'key'            => $key,
            'title'          => (string) ($acf['title'] ?? $key),
            'active'         => ! isset($acf['active']) || (bool) $acf['active'],
            'location'       => $location,
            'locationAny'    => $locationAny,
            'position'       => $position === 'acf_after_title' ? 'after_title' : ($position === 'side' ? 'side' : 'normal'),
            'style'          => ($acf['style'] ?? '') === 'seamless' ? 'seamless' : 'default',
            'labelPlacement' => ($acf['label_placement'] ?? '') === 'left' ? 'left' : 'top',
            'fields'         => $fields,
        ];

        // Conditions reference ACF field keys; resolve them now that all keys are known.
        $group['fields'] = $this->resolveConditions($group['fields']);

        return ['group' => $group, 'warnings' => $this->warnings, 'fieldKeys' => $this->fieldKeys, 'names' => $names];
    }

    /**
     * A CtrlField key for an ACF field name. Case is kept (templates read
     * contentHtml, not contenthtml); hyphens, spaces and accents are not.
     */
    public static function keyFor(string $acfName): string
    {
        if (function_exists('remove_accents')) {
            $acfName = remove_accents($acfName); // "Opções" → "Opcoes"
        }
        $key = (string) preg_replace('/[^A-Za-z0-9_]+/', '_', $acfName);
        $key = trim($key, '_');
        if ($key === '' || ! ctype_alpha($key[0])) {
            $key = 'f_' . $key;
        }

        return substr($key, 0, 64);
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<mixed>          $acfFields
     * @param  array<string, string> $names
     * @return list<array<string, mixed>>
     */
    private function convertFields(array $acfFields, array &$names): array
    {
        $out = [];
        foreach ($acfFields as $f) {
            if (! is_array($f)) {
                continue;
            }
            $field = $this->convertField($f, $names);
            if ($field !== null) {
                $out[] = $field;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  array<string, string> $names
     * @return array<string, mixed>|null
     */
    private function convertField(array $f, array &$names): ?array
    {
        $acfType = (string) ($f['type'] ?? '');
        $label   = (string) ($f['label'] ?? '');
        $name    = (string) ($f['name'] ?? '');
        $shown   = $label !== '' ? $label : $name;

        if (! isset(self::TYPES[$acfType])) {
            $this->warnings[] = sprintf(__('Field "%1$s": the ACF type "%2$s" is not supported and was skipped.', 'ctrlfield'), $shown, $acfType);
            return null;
        }

        $type = self::TYPES[$acfType];
        // An accordion with "endpoint" closes the open one instead of starting a new one.
        if ($acfType === 'accordion' && ! empty($f['endpoint'])) {
            $type = 'accordion_end';
        }
        // Tabs and messages have no name in ACF.
        $key = self::keyFor($name !== '' ? $name : ($type . '_' . substr(md5((string) ($f['key'] ?? $label)), 0, 6)));
        if ($name !== '' && $key !== $name) {
            $names[$name]     = $key;
            $this->warnings[] = sprintf(__('Field "%1$s" was renamed from "%2$s" to "%3$s"; update templates that use the old name.', 'ctrlfield'), $shown, $name, $key);
        }
        if (is_string($f['key'] ?? null)) {
            $this->fieldKeys[$f['key']] = $key;
        }

        $out = ['key' => $key, 'type' => $type, 'label' => $label];

        if (! empty($f['required'])) {
            $out['required'] = true;
        }
        foreach (['instructions' => 'instructions', 'placeholder' => 'placeholder'] as $from => $to) {
            if (is_string($f[$from] ?? null) && $f[$from] !== '') {
                $out[$to] = $f[$from];
            }
        }
        if (isset($f['default_value']) && is_scalar($f['default_value']) && (string) $f['default_value'] !== '') {
            $out['default'] = (string) $f['default_value'];
        }
        $width = (int) ($f['wrapper']['width'] ?? 0);
        if ($width > 0 && $width < 100) {
            $out['width'] = $width <= 37 ? 25 : ($width <= 62 ? 50 : 75);
        }
        if (! empty($f['conditional_logic']) && is_array($f['conditional_logic'])) {
            $out['_acfCondition'] = $f['conditional_logic'];
        }

        $rf = is_string($f['return_format'] ?? null) ? $f['return_format'] : '';

        switch ($acfType) {
            case 'range':
                foreach (['min', 'max', 'step'] as $k) {
                    if (isset($f[$k]) && is_numeric($f[$k])) {
                        $out[$k] = 0 + $f[$k];
                    }
                }
                break;
            case 'select':
                if (! empty($f['multiple'])) {
                    $out['type']      = 'checkbox';
                    $this->warnings[] = sprintf(__('Field "%s": a multiple select became checkboxes.', 'ctrlfield'), $shown);
                }
                // no break
            case 'checkbox':
            case 'radio':
            case 'button_group':
                $out['options'] = self::choices($f['choices'] ?? []);
                if (! empty($f['allow_null']) && $acfType === 'button_group') {
                    $out['allowNull'] = true;
                }
                if (in_array($rf, ['label', 'array'], true)) {
                    $out['returnFormat'] = $rf;
                }
                if ($out['type'] === 'checkbox') {
                    unset($out['default']);
                }
                break;
            case 'wysiwyg':
                $toolbar = strtolower((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($f['toolbar'] ?? 'full')));
                if ($toolbar !== '' && $toolbar !== 'full') {
                    $out['toolbar'] = $toolbar;
                }
                if (isset($f['media_upload']) && empty($f['media_upload'])) {
                    $out['noMedia'] = true;
                }
                break;
            case 'true_false':
                if (is_string($f['message'] ?? null) && $f['message'] !== '') {
                    $out['message'] = $f['message'];
                }
                unset($out['default']);
                break;
            case 'image':
            case 'file':
            case 'gallery':
                $out['returnFormat'] = in_array($rf, ['id', 'url', 'array'], true) ? $rf : 'array';
                if ($acfType === 'gallery') {
                    self::copyCount($f, $out, 'min', 'minItems');
                    self::copyCount($f, $out, 'max', 'maxItems');
                }
                break;
            case 'link':
                $out['returnFormat'] = $rf === 'url' ? 'url' : 'array';
                break;
            case 'post_object':
            case 'page_link':
                $types = self::list($f['post_type'] ?? []);
                if ($types !== []) {
                    $out['postType'] = $types;
                }
                if (! empty($f['multiple'])) {
                    $out['multiple'] = true;
                }
                if ($acfType === 'post_object') {
                    $out['returnFormat'] = $rf === 'id' ? 'id' : 'object';
                }
                break;
            case 'relationship':
                $types = self::list($f['post_type'] ?? []);
                if ($types !== []) {
                    $out['relatedPostType'] = $types[0];
                    if (count($types) > 1) {
                        $this->warnings[] = sprintf(__('Field "%1$s": CtrlField relationships hold one post type; kept "%2$s".', 'ctrlfield'), $shown, $types[0]);
                    }
                } else {
                    $out['relatedPostType'] = 'post';
                    $this->warnings[] = sprintf(__('Field "%s": the relationship allowed every post type; it now uses "post".', 'ctrlfield'), $shown);
                }
                self::copyCount($f, $out, 'min', 'minItems');
                self::copyCount($f, $out, 'max', 'maxItems');
                $out['returnFormat'] = $rf === 'id' ? 'id' : 'object';
                break;
            case 'taxonomy':
                $out['taxonomy'] = is_string($f['taxonomy'] ?? null) && $f['taxonomy'] !== '' ? $f['taxonomy'] : 'category';
                $ft              = (string) ($f['field_type'] ?? 'checkbox');
                $out['multiple'] = in_array($ft, ['checkbox', 'multi_select'], true);
                $out['appearance'] = match ($ft) {
                    'radio'    => 'radio',
                    'checkbox' => 'checkbox',
                    default    => 'select',
                };
                if (! $out['multiple']) {
                    unset($out['multiple']);
                }
                $out['returnFormat'] = $rf === 'object' ? 'object' : 'id';
                break;
            case 'user':
                $roles = self::list($f['role'] ?? []);
                if ($roles !== []) {
                    $out['roles'] = $roles;
                }
                if (! empty($f['multiple'])) {
                    $out['multiple'] = true;
                }
                $out['returnFormat'] = in_array($rf, ['id', 'object', 'array'], true) ? $rf : 'array';
                break;
            case 'date_picker':
            case 'date_time_picker':
            case 'time_picker':
                $defaults = ['date_picker' => 'd/m/Y', 'date_time_picker' => 'd/m/Y g:i a', 'time_picker' => 'g:i a'];
                $out['returnFormat'] = $rf !== '' ? $rf : $defaults[$acfType];
                if ($acfType !== 'time_picker' && is_string($f['display_format'] ?? null) && $f['display_format'] !== '') {
                    $out['format'] = $f['display_format'];
                }
                break;
            case 'message':
                $out['content'] = (string) ($f['message'] ?? '');
                break;
            case 'accordion':
                if ($type === 'accordion' && empty($f['open'])) {
                    $out['closed'] = true; // ACF accordions start closed unless "open" is set
                }
                break;
            case 'group':
            case 'repeater':
                $out['fields'] = $this->convertFields(is_array($f['sub_fields'] ?? null) ? $f['sub_fields'] : [], $names);
                if ($acfType === 'repeater') {
                    self::copyCount($f, $out, 'min', 'minItems');
                    self::copyCount($f, $out, 'max', 'maxItems');
                    if (is_string($f['button_label'] ?? null) && $f['button_label'] !== '') {
                        $out['buttonLabel'] = $f['button_label'];
                    }
                }
                break;
            case 'flexible_content':
                $out['layouts'] = [];
                foreach (is_array($f['layouts'] ?? null) ? $f['layouts'] : [] as $layout) {
                    if (! is_array($layout)) {
                        continue;
                    }
                    $converted = [
                        'key'    => self::keyFor((string) ($layout['name'] ?? 'layout')),
                        'label'  => (string) ($layout['label'] ?? ''),
                        'fields' => $this->convertFields(is_array($layout['sub_fields'] ?? null) ? $layout['sub_fields'] : [], $names),
                    ];
                    if (is_numeric($layout['max'] ?? null) && (int) $layout['max'] > 0) {
                        $converted['max'] = (int) $layout['max'];
                    }
                    $out['layouts'][] = $converted;
                }
                if (is_string($f['button_label'] ?? null) && $f['button_label'] !== '') {
                    $out['buttonLabel'] = $f['button_label'];
                }
                break;
        }

        return $out;
    }

    /**
     * ACF conditional logic: OR of AND groups referencing field keys. CtrlField
     * keeps one condition per field (a single rule in a single group).
     *
     * @param  list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function resolveConditions(array $fields): array
    {
        $ops = ['==' => '==', '!=' => '!=', '==empty' => 'empty', '!=empty' => 'not_empty',
            '==contains' => 'contains', '>' => '>', '<' => '<'];

        foreach ($fields as $i => $field) {
            foreach (['fields'] as $nested) {
                if (isset($field[$nested])) {
                    $fields[$i][$nested] = $this->resolveConditions($field[$nested]);
                }
            }
            foreach ($field['layouts'] ?? [] as $l => $layout) {
                $fields[$i]['layouts'][$l]['fields'] = $this->resolveConditions($layout['fields']);
            }

            if (! isset($field['_acfCondition'])) {
                continue;
            }
            $logic = $field['_acfCondition'];
            unset($fields[$i]['_acfCondition']);

            $rule = $logic[0][0] ?? null;
            $ok   = count($logic) === 1 && is_array($logic[0]) && count($logic[0]) === 1 && is_array($rule)
                && isset($this->fieldKeys[$rule['field'] ?? ''], $ops[$rule['operator'] ?? '']);

            if (! $ok) {
                $this->warnings[] = sprintf(__('Field "%s": its conditional logic has several rules; only simple conditions are imported, so it is always shown.', 'ctrlfield'), $field['label'] ?: $field['key']);
                continue;
            }

            $fields[$i]['visibleWhen'] = [
                'field'    => $this->fieldKeys[$rule['field']],
                'operator' => $ops[$rule['operator']],
                'value'    => is_scalar($rule['value'] ?? '') ? (string) ($rule['value'] ?? '') : '',
            ];
        }

        return $fields;
    }

    /**
     * ACF location: OR of AND groups. CtrlField has AND rules plus one "any of"
     * list, so: one group → AND; several one-rule groups → any of.
     *
     * @param  array<mixed> $acfLocation
     * @return array{0: list<array<string, string>>, 1: list<array<string, string>>}
     */
    private function convertLocation(array $acfLocation): array
    {
        $groups = [];
        foreach ($acfLocation as $and) {
            if (! is_array($and)) {
                continue;
            }
            $rules = [];
            foreach ($and as $rule) {
                $r = is_array($rule) ? $this->convertRule($rule) : null;
                if ($r !== null) {
                    $rules[] = $r;
                }
            }
            if ($rules !== []) {
                $groups[] = $rules;
            }
        }

        if ($groups === []) {
            return [[], []];
        }
        if (count($groups) === 1) {
            return [$groups[0], []];
        }
        if (max(array_map('count', $groups)) === 1) {
            return [[], array_merge(...$groups)];
        }

        $this->warnings[] = __('The location has several groups of rules; only the first group was imported.', 'ctrlfield');

        return [$groups[0], []];
    }

    /**
     * @param  array<string, mixed> $rule
     * @return array{key: string, operator: string, value: string}|null
     */
    private function convertRule(array $rule): ?array
    {
        $param = (string) ($rule['param'] ?? '');
        $op    = ($rule['operator'] ?? '==') === '!=' ? '!=' : '==';
        $value = is_scalar($rule['value'] ?? null) ? (string) $rule['value'] : '';

        if (! array_key_exists($param, self::PARAMS)) {
            $this->warnings[] = sprintf(__('Location rule "%s" is not supported and was skipped.', 'ctrlfield'), $param);
            return null;
        }

        return match ($param) {
            'user_form'     => ['key' => 'context', 'operator' => '==', 'value' => 'user_profile'],
            'comment'       => ['key' => 'context', 'operator' => '==', 'value' => 'comment'],
            'nav_menu_item' => ['key' => 'context', 'operator' => '==', 'value' => 'nav_menu_item'],
            'taxonomy'      => $value === 'all'
                ? $this->unsupported(__('Location "taxonomy is all" is not supported; pick the taxonomies after the import.', 'ctrlfield'))
                : ['key' => 'taxonomy', 'operator' => $op, 'value' => $value],
            default         => ['key' => (string) self::PARAMS[$param], 'operator' => $op, 'value' => $value],
        };
    }

    private function unsupported(string $warning): null
    {
        $this->warnings[] = $warning;
        return null;
    }

    /** @return list<array{0: string, 1: string}> */
    private static function choices(mixed $choices): array
    {
        $out = [];
        foreach (is_array($choices) ? $choices : [] as $value => $label) {
            $out[] = [(string) $value, is_scalar($label) ? (string) $label : (string) $value];
        }

        return $out;
    }

    /** @return list<string> */
    private static function list(mixed $v): array
    {
        $list = is_array($v) ? $v : (is_string($v) && $v !== '' ? [$v] : []);

        return array_values(array_filter(array_map('strval', $list)));
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    private static function copyCount(array $from, array &$to, string $acf, string $ours): void
    {
        if (isset($from[$acf]) && is_numeric($from[$acf]) && (int) $from[$acf] > 0) {
            $to[$ours] = (int) $from[$acf];
        }
    }
}
