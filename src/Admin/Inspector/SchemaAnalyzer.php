<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Conditions\ContextKey;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * What the Schema Inspector shows, worked out from the registered field
 * groups alone (no database): each group's origin and places in plain words,
 * its whole field tree, and the problems a schema can have by itself.
 */
final class SchemaAnalyzer
{
    /**
     * @param array<string, FieldGroup> $groups  FieldRegistry::all()
     * @param list<string>              $json    keys of groups made in Field Groups (JSON)
     * @param list<string>              $acf     keys of groups registered from ACF
     */
    public function __construct(
        private readonly array $groups,
        private readonly array $json = [],
        private readonly array $acf = [],
    ) {}

    /** 'json' | 'acf' | 'block' | 'code' */
    public function source(string $key, FieldGroup $group): string
    {
        return match (true) {
            in_array($key, $this->json, true) => 'json',
            in_array($key, $this->acf, true)  => 'acf',
            $group->isBlock()                 => 'block',
            default                           => 'code',
        };
    }

    /**
     * Post types a group appears on (its post_type "==" rules, in any OR set).
     *
     * @return list<string>
     */
    public static function postTypes(FieldGroup $group): array
    {
        $types = [];
        foreach ([$group->getAndConditions(), ...$group->getOrGroups()] as $rules) {
            foreach ($rules as $rule) {
                if ($rule['key'] === 'post_type' && $rule['operator'] === '==') {
                    $types[] = (string) $rule['value'];
                }
            }
        }

        return array_values(array_unique($types));
    }

    /** 'post' | 'options' | 'term' | 'user' | 'comment' | 'other' — the kind of screen, for filters. */
    public static function place(FieldGroup $group): string
    {
        foreach ([$group->getAndConditions(), ...$group->getOrGroups()] as $rules) {
            foreach ($rules as $rule) {
                $key   = $rule['key'];
                $value = (string) $rule['value'];
                if ($key === 'options_page') {
                    return 'options';
                }
                if ($key === 'taxonomy') {
                    return 'term';
                }
                if ($key === 'context') {
                    return $value === 'comment' ? 'comment' : 'user';
                }
                if (in_array($key, ['post_type', 'page_template', 'page_type', 'post_format', 'post_status', 'post_category', 'post_taxonomy', 'page', 'page_parent'], true)) {
                    return 'post';
                }
            }
        }

        return 'other';
    }

    /**
     * Where the group shows, in plain words: every ->where() rule must hold
     * (joined with " · "), and one rule of each ->whereAny() set ("or").
     *
     * @param callable(string): string $postTypeLabel
     */
    public static function where(FieldGroup $group, callable $postTypeLabel): string
    {
        $describe = static function (array $rule) use ($postTypeLabel): string {
            $key   = (string) ($rule['key'] ?? '');
            $value = (string) ($rule['value'] ?? '');
            $not   = ($rule['operator'] ?? '==') === '!=';
            return match ($key) {
                /* translators: %s: post type name */
                'post_type'     => sprintf($not ? __('Post type is not %s', 'ctrlfield') : __('Post type is %s', 'ctrlfield'), $postTypeLabel($value)),
                /* translators: %s: options page slug */
                'options_page'  => sprintf(__('Options page %s', 'ctrlfield'), $value),
                /* translators: %s: taxonomy */
                'taxonomy'      => sprintf(__('Terms of %s', 'ctrlfield'), $value),
                'context'       => $value === 'comment' ? __('Comments', 'ctrlfield') : __('User profile', 'ctrlfield'),
                /* translators: %s: page template */
                'page_template' => sprintf($not ? __('Template is not %s', 'ctrlfield') : __('Template is %s', 'ctrlfield'), $value === 'default' ? __('Default', 'ctrlfield') : $value),
                default         => trim($key . ' ' . ($rule['operator'] ?? '') . ' ' . $value),
            };
        };

        $parts = array_map($describe, $group->getAndConditions());
        foreach ($group->getOrGroups() as $set) {
            $any = array_map($describe, $set);
            // "Post type is Project or Post type is Page" → "Post type is Project or Page"
            $types = array_filter($set, static fn (array $r): bool => $r['key'] === 'post_type' && $r['operator'] === '==');
            if (count($types) === count($set) && $set !== []) {
                /* translators: %s: post type names joined with "or" */
                $any = [sprintf(__('Post type is %s', 'ctrlfield'), implode(' ' . __('or', 'ctrlfield') . ' ', array_map(static fn (array $r): string => $postTypeLabel((string) $r['value']), $set)))];
            }
            $parts[] = implode(' ' . __('or', 'ctrlfield') . ' ', $any);
        }

        return $parts === [] ? __('Everywhere (no location rule)', 'ctrlfield') : implode(' · ', $parts);
    }

    /**
     * The whole field tree: sub-fields of groups and repeaters, flexible
     * content layouts with theirs.
     *
     * @param  array<int, FieldDefinition> $fields
     * @return list<array<string, mixed>>
     */
    public static function tree(array $fields, string $path = ''): array
    {
        $nodes = [];
        foreach ($fields as $field) {
            $def  = $field->getDefinition();
            $key  = $field->getKey();
            $here = $path === '' ? $key : $path . '.' . $key;
            $node = [
                'key'       => $key,
                'path'      => $here,
                'label'     => (string) ($def['label'] ?? ''),
                'type'      => $field->getType()->value,
                'ui'        => $field->isUiOnly(),
                'required'  => $field->isRequired(),
                'width'     => $field->getWidth(),
                'return'    => (string) ($def['return_format'] ?? $field->getReturnFormat()),
                'condition' => self::conditionText($field),
                'children'  => [],
                'layouts'   => [],
            ];
            if ($field instanceof FlexibleContentInterface) {
                $labels = [];
                if (method_exists($field, 'getLayouts')) {
                    foreach ((array) $field->getLayouts() as $layoutKey => $layout) {
                        $labels[(string) $layoutKey] = is_object($layout) && method_exists($layout, 'getLabel') ? (string) $layout->getLabel() : '';
                    }
                }
                foreach ($field->getLayoutKeys() as $layoutKey) {
                    $node['layouts'][] = [
                        'key'    => $layoutKey,
                        'label'  => $labels[$layoutKey] ?? '',
                        'fields' => self::tree(array_values($field->getLayoutFields($layoutKey)), $here . '[' . $layoutKey . ']'),
                    ];
                }
            } elseif ($field instanceof NestedFieldInterface) {
                $node['children'] = self::tree(array_values($field->getFields()), $here);
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Number of fields in a tree, sub-fields and layout fields included (not tabs / accordions / messages).
     *
     * @param list<array<string, mixed>> $tree
     */
    public static function count(array $tree): int
    {
        $n = 0;
        foreach ($tree as $node) {
            $n += $node['ui'] ? 0 : 1;
            $n += self::count($node['children']);
            foreach ($node['layouts'] as $layout) {
                $n += self::count($layout['fields']);
            }
        }

        return $n;
    }

    /** "Shown when layout = b and title is not empty" — '' without a condition. */
    public static function conditionText(FieldDefinition $field): string
    {
        $group = $field->getConditionGroup();
        if ($group === null) {
            return '';
        }
        $words = [
            '==' => '=', '!=' => '≠', 'contains' => __('contains', 'ctrlfield'), 'empty' => __('is empty', 'ctrlfield'),
            'not_empty' => __('is not empty', 'ctrlfield'), '>' => '>', '<' => '<',
        ];
        $parts = [];
        foreach ($group->getConditions() as [$key, $operator, $value]) {
            $op      = $words[$operator] ?? $operator;
            $parts[] = in_array($operator, ['empty', 'not_empty'], true)
                ? "{$key} {$op}"
                : "{$key} {$op} " . (is_scalar($value) ? (string) $value : json_encode($value));
        }

        return sprintf(
            __('Shown when %s', 'ctrlfield'),
            implode($group->getType() === 'any' ? ' ' . __('or', 'ctrlfield') . ' ' : ' ' . __('and', 'ctrlfield') . ' ', $parts)
        );
    }

    /**
     * Problems the schema has by itself:
     *  - the same field key in two groups on the same post type (one overwrites the other);
     *  - conditions on a field that is not a sibling (and no context key);
     *  - fields without a label.
     *
     * @return list<array<string, string>> level ('error'|'warning'|'info'), code, group, field, message,
     *                                     layout (the flexible content layout the field is in, '' for none)
     */
    public function issues(): array
    {
        $issues = [];

        // Same key, same post type.
        $owners = []; // post type => field key => group keys
        foreach ($this->groups as $groupKey => $group) {
            foreach (self::postTypes($group) as $type) {
                foreach ($group->getFields() as $field) {
                    if (! $field->isUiOnly()) {
                        $owners[$type][$field->getKey()][] = (string) $groupKey;
                    }
                }
            }
        }
        foreach ($owners as $type => $keys) {
            foreach ($keys as $fieldKey => $groupKeys) {
                $groupKeys = array_values(array_unique($groupKeys));
                if (count($groupKeys) > 1) {
                    $issues[] = [
                        'level'   => 'warning',
                        'code'    => 'duplicate_key',
                        'group'   => implode(', ', $groupKeys),
                        'field'   => (string) $fieldKey,
                        'layout'  => '',
                        'message' => sprintf(__('The key "%1$s" is used by %2$s on %3$s: they share one stored value, so saving one changes the other.', 'ctrlfield'), $fieldKey, implode(' + ', $groupKeys), $type),
                    ];
                }
            }
        }

        // Conditions and labels, at every level.
        $context = array_map(static fn (ContextKey $c): string => $c->value, ContextKey::cases());
        foreach ($this->groups as $groupKey => $group) {
            $this->walk(array_values($group->getFields()), (string) $groupKey, $context, $issues);
        }

        return $issues;
    }

    /**
     * @param list<FieldDefinition> $fields
     * @param list<string>          $context
     * @param list<array<string, string>> $issues
     * @param list<string>          $outer  keys of the levels around (a row's condition may look up)
     * @param string                $layout the layout these fields are in ('' outside flexible content)
     */
    private function walk(array $fields, string $groupKey, array $context, array &$issues, array $outer = [], string $layout = ''): void
    {
        $siblings = [...array_map(static fn (FieldDefinition $f): string => $f->getKey(), $fields), ...$outer];
        foreach ($fields as $field) {
            $condition = $field->getConditionGroup();
            foreach ($condition?->getConditions() ?? [] as [$target]) {
                if (! in_array($target, $siblings, true) && ! in_array($target, $context, true)) {
                    $issues[] = [
                        'level'   => 'warning',
                        'code'    => 'condition_target',
                        'group'   => $groupKey,
                        'field'   => $field->getKey(),
                        'layout'  => $layout,
                        'message' => sprintf(__('"%1$s" is shown depending on "%2$s", which is not a field next to it: the condition never changes.', 'ctrlfield'), $field->getKey(), $target),
                    ];
                }
            }
            if (! $field->isUiOnly() && trim((string) ($field->getDefinition()['label'] ?? '')) === '') {
                $issues[] = [
                    'level'   => 'info',
                    'code'    => 'no_label',
                    'group'   => $groupKey,
                    'field'   => $field->getKey(),
                    'layout'  => $layout,
                    'message' => sprintf(__('"%s" has no label: editors see its key.', 'ctrlfield'), $field->getKey()),
                ];
            }
            if ($field instanceof FlexibleContentInterface) {
                foreach ($field->getLayoutKeys() as $layoutKey) {
                    $this->walk(array_values($field->getLayoutFields($layoutKey)), $groupKey, $context, $issues, $siblings, (string) $layoutKey);
                }
            } elseif ($field instanceof NestedFieldInterface) {
                $this->walk(array_values($field->getFields()), $groupKey, $context, $issues, $siblings, $layout);
            }
        }
    }
}
