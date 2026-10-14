<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * What templates receive from a field group, as types for the editor:
 * a PHP array shape (/** @var array{…} $data *\/, PHPStan / Psalm / PhpStorm)
 * and a TypeScript interface. Follows each field's type, return format,
 * ->multiple() and the shape of groups, repeaters and flexible layouts.
 */
final class TypesGenerator
{
    public function php(FieldGroup $group, string $variable = '$data'): string
    {
        return "/**\n * " . ($group->getTitle() !== '' ? $group->getTitle() : $group->getKey()) . " ({$group->getKey()})\n *\n * @var "
            . $this->phpShape(array_values($group->getFields()), 1) . " {$variable}\n */";
    }

    public function typescript(FieldGroup $group): string
    {
        $name = self::interfaceName($group->getKey());

        return "/** " . ($group->getTitle() !== '' ? $group->getTitle() : $group->getKey()) . " ({$group->getKey()}) */\n"
            . "export interface {$name} " . $this->tsShape(array_values($group->getFields()), 0);
    }

    /** "project_free_fields" → "ProjectFreeFields" */
    public static function interfaceName(string $key): string
    {
        $name = str_replace(' ', '', ucwords((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $key)));

        return preg_match('/^[A-Za-z]/', $name) ? $name : 'Group' . $name;
    }

    // ── PHP ──────────────────────────────────────────────────────────────────

    /** @param list<FieldDefinition> $fields */
    private function phpShape(array $fields, int $depth): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if ($field->isUiOnly()) {
                continue;
            }
            $lines[] = self::line($depth) . $this->phpKey($field->getKey()) . ': ' . $this->phpType($field, $depth);
        }

        return $lines === [] ? 'array{}' : "array{\n" . implode(",\n", $lines) . "\n" . self::line($depth - 1) . '}';
    }

    /** A docblock line indented to a nesting depth: " *         ". */
    private static function line(int $depth): string
    {
        return ' * ' . str_repeat('    ', $depth);
    }

    private function phpKey(string $key): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) ? $key : "'" . $key . "'";
    }

    private function phpType(FieldDefinition $field, int $depth): string
    {
        $def      = $field->getDefinition();
        $type     = $field->getType()->value;
        $rf       = $field->getReturnFormat();
        $multiple = (bool) ($def['multiple'] ?? false);
        $list     = static fn (string $t): string => "list<{$t}>";
        $image    = 'array{id: int, url: string, width: int, height: int, alt: string}|null';

        return match (true) {
            $field instanceof FlexibleContentInterface => $list($this->phpLayouts($field, $depth)),
            $type === 'repeater' && $field instanceof NestedFieldInterface => $list($this->phpShape(array_values($field->getFields()), $depth + 1)),
            $type === 'group' && $field instanceof NestedFieldInterface    => $this->phpShape(array_values($field->getFields()), $depth + 1),
            $type === 'number', $type === 'range'                          => 'int|float|null',
            $type === 'true_false'                                         => 'bool',
            $type === 'checkbox'                                           => $list('string'),
            $type === 'image', $type === 'file'                            => match ($rf) { 'url' => 'string', 'array' => $image, default => 'int' },
            $type === 'gallery'                                            => $list(match ($rf) { 'url' => 'string', 'array' => 'array{id: int, url: string, width: int, height: int, alt: string}', default => 'int' }),
            $type === 'link'                                               => $multiple
                ? $list('array{url: string, title: string, target: string, style?: string, icon?: string, icon_position?: string}')
                : ($rf === 'url' ? 'string' : 'array{url: string, title: string, target: string, style?: string, icon?: string, icon_position?: string}|null'),
            $type === 'page_link'                                          => $multiple ? $list('string') : 'string',
            in_array($type, ['post_object', 'relationship'], true)         => ($multiple || $type === 'relationship') ? $list($rf === 'object' ? '\\WP_Post' : 'int') : ($rf === 'object' ? '\\WP_Post|null' : 'int|null'),
            $type === 'taxonomy_term', $type === 'user'                    => $this->phpEntity($type, $rf, $multiple),
            $type === 'map'                                                => 'array{lat: float, lng: float, zoom: int, address: string}|null',
            in_array($type, ['date', 'datetime', 'time'], true) && $rf === 'DateTime' => '\\DateTimeImmutable|null',
            default                                                        => 'string',
        };
    }

    private function phpEntity(string $type, string $rf, bool $multiple): string
    {
        $one = match ($rf) {
            'object' => $type === 'user' ? '\\WP_User' : '\\WP_Term',
            'array'  => $type === 'user'
                ? 'array{ID: int, user_firstname: string, user_lastname: string, nickname: string, user_nicename: string, display_name: string, user_email: string, user_url: string, user_registered: string, user_description: string, user_avatar: string}'
                : 'array{term_id: int, name: string, slug: string, taxonomy: string, description: string, count: int, url: string}',
            default  => 'int',
        };
        if ($multiple) {
            return "list<{$one}>";
        }

        return $rf === 'array' ? $one . '|array{}' : $one . '|null';
    }

    private function phpLayouts(FlexibleContentInterface $field, int $depth): string
    {
        $shapes = [];
        foreach ($field->getLayoutKeys() as $layout) {
            $shape    = $this->phpShape(array_values($field->getLayoutFields($layout)), $depth + 1);
            $head     = "array{\n" . self::line($depth + 1) . "_layout: '{$layout}'";
            $shapes[] = $shape === 'array{}'
                ? $head . "\n" . self::line($depth) . '}'
                : $head . ",\n" . substr($shape, strlen("array{\n"));
        }

        return $shapes === [] ? 'array{_layout: string}' : implode('|', $shapes);
    }

    // ── TypeScript ───────────────────────────────────────────────────────────

    /** @param list<FieldDefinition> $fields */
    private function tsShape(array $fields, int $depth): string
    {
        $pad   = str_repeat('  ', $depth + 1);
        $lines = [];
        foreach ($fields as $field) {
            if ($field->isUiOnly()) {
                continue;
            }
            $key     = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $field->getKey()) ? $field->getKey() : "'{$field->getKey()}'";
            $lines[] = $pad . $key . ': ' . $this->tsType($field, $depth + 1) . ';';
        }

        return $lines === [] ? '{}' : "{\n" . implode("\n", $lines) . "\n" . str_repeat('  ', $depth) . '}';
    }

    private function tsType(FieldDefinition $field, int $depth): string
    {
        $def      = $field->getDefinition();
        $type     = $field->getType()->value;
        $rf       = $field->getReturnFormat();
        $multiple = (bool) ($def['multiple'] ?? false);
        $image    = '{ id: number; url: string; width: number; height: number; alt: string }';
        $link     = '{ url: string; title: string; target: string; style?: string; icon?: string; icon_position?: string }';

        return match (true) {
            $field instanceof FlexibleContentInterface => 'Array<' . $this->tsLayouts($field, $depth) . '>',
            $type === 'repeater' && $field instanceof NestedFieldInterface => 'Array<' . $this->tsShape(array_values($field->getFields()), $depth) . '>',
            $type === 'group' && $field instanceof NestedFieldInterface    => $this->tsShape(array_values($field->getFields()), $depth),
            $type === 'number', $type === 'range'                          => 'number | null',
            $type === 'true_false'                                         => 'boolean',
            $type === 'checkbox'                                           => 'string[]',
            $type === 'image', $type === 'file'                            => match ($rf) { 'url' => 'string', 'array' => $image . ' | null', default => 'number' },
            $type === 'gallery'                                            => match ($rf) { 'url' => 'string[]', 'array' => "Array<{$image}>", default => 'number[]' },
            $type === 'link'                                               => $multiple ? "Array<{$link}>" : ($rf === 'url' ? 'string' : $link . ' | null'),
            $type === 'page_link'                                          => $multiple ? 'string[]' : 'string',
            in_array($type, ['post_object', 'relationship'], true)         => ($multiple || $type === 'relationship') ? 'number[]' : 'number | null',
            $type === 'user' && $rf === 'array'                            => $multiple ? 'Array<{ ID: number; user_firstname: string; user_lastname: string; nickname: string; user_nicename: string; display_name: string; user_email: string; user_url: string; user_registered: string; user_description: string; user_avatar: string }>' : '{ ID: number; user_firstname: string; user_lastname: string; nickname: string; user_nicename: string; display_name: string; user_email: string; user_url: string; user_registered: string; user_description: string; user_avatar: string } | null',
            $type === 'taxonomy_term' && $rf === 'array'                   => $multiple ? 'Array<{ term_id: number; name: string; slug: string; taxonomy: string; url: string }>' : '{ term_id: number; name: string; slug: string; taxonomy: string; url: string } | null',
            $type === 'user', $type === 'taxonomy_term'                    => $multiple ? 'number[]' : 'number | null',
            $type === 'map'                                                => '{ lat: number; lng: number; zoom: number; address: string } | null',
            default                                                        => 'string',
        };
    }

    private function tsLayouts(FlexibleContentInterface $field, int $depth): string
    {
        $shapes = [];
        foreach ($field->getLayoutKeys() as $layout) {
            $body     = $this->tsShape(array_values($field->getLayoutFields($layout)), $depth);
            $shapes[] = $body === '{}'
                ? "{ _layout: '{$layout}' }"
                : "{\n" . str_repeat('  ', $depth + 1) . "_layout: '{$layout}';" . substr($body, 1);
        }

        return $shapes === [] ? '{ _layout: string }' : implode(' | ', $shapes);
    }
}
