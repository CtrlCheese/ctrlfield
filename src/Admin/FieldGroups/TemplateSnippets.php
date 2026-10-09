<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

use CtrlField\Builder\FieldGroup;
use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * Template code for a field group ("Template code" in Field Groups): how to
 * print each field in a theme, escaped the right way for its type.
 *
 * Dialects: php (ctrlfield_get), blade, twig (Timber) and acf (get_field /
 * have_rows, for themes written for ACF).
 */
final class TemplateSnippets
{
    public const DIALECTS = ['php', 'blade', 'twig', 'acf'];

    /** @var list<string> */
    private array $lines = [];

    private int $depth = 0;

    /**
     * @param string                     $dialect php | blade | twig | acf
     * @param array{type: string, page?: string} $entity where the values live: post, term, user or options (+ page slug)
     */
    private function __construct(private readonly string $dialect, private readonly array $entity) {}

    public static function generate(FieldGroup $group, string $dialect): string
    {
        $dialect = in_array($dialect, self::DIALECTS, true) ? $dialect : 'php';
        $writer  = new self($dialect, self::entity($group));
        $writer->intro($group);
        foreach ($group->getFields() as $field) {
            $writer->field($field, null);
        }

        return implode("\n", $writer->lines) . "\n";
    }

    /**
     * Where the group's values are stored, from its location rules.
     *
     * @return array{type: string, page?: string}
     */
    public static function entity(FieldGroup $group): array
    {
        foreach ($group->getAndConditions() as $c) {
            $key = $c['key'];
            if ($key === 'options_page') {
                return ['type' => 'options', 'page' => is_scalar($c['value'] ?? null) ? (string) $c['value'] : 'options'];
            }
            if ($key === 'taxonomy') {
                return ['type' => 'term'];
            }
            if ($key === 'user_role' || $key === 'user_form' || ($key === 'context' && ($c['value'] ?? '') === 'user')) {
                return ['type' => 'user'];
            }
        }

        return ['type' => 'post'];
    }

    // -------------------------------------------------------------------------

    private function intro(FieldGroup $group): void
    {
        $where = match ($this->entity['type']) {
            'term'    => 'a term archive (taxonomy-*.php): $term = get_queried_object();',
            'user'    => 'an author page: $user_id = get_queried_object_id();',
            'options' => 'any template (options page "' . ($this->entity['page'] ?? '') . '")',
            default   => 'the post template (single.php, page.php…), inside the loop',
        };
        $this->comment(($group->getTitle() !== '' ? $group->getTitle() : $group->getKey()) . ' — use in ' . $where);
    }

    /** Prints one field; $row is the loop variable it belongs to (null = top level). */
    private function field(FieldDefinition $f, ?string $row): void
    {
        $type = $f->getType();
        if (in_array($type, [FieldType::TAB, FieldType::ACCORDION, FieldType::ACCORDION_END, FieldType::MESSAGE, FieldType::SEPARATOR, FieldType::PASSWORD], true)) {
            return;
        }

        $key = $f->getKey();
        $var = $this->varName($key);
        $get = $this->get($key, $row);

        switch ($type) {
            case FieldType::WYSIWYG:
                $this->html($get, 'div');
                return;
            case FieldType::TEXTAREA:
                $this->textarea($get);
                return;
            case FieldType::URL:
                $this->assign($var, $get);
                $this->ifSet($var, fn () => $this->line('<a href="' . $this->url($var) . '">' . $this->text($var) . '</a>'));
                return;
            case FieldType::EMAIL:
                $this->assign($var, $get);
                $this->ifSet($var, fn () => $this->line('<a href="mailto:' . $this->attr($var) . '">' . $this->text($var) . '</a>'));
                return;
            case FieldType::LINK:
                $this->assign($var, $get);
                $this->ifSet($this->index($var, 'url'), fn () => $this->line('<a ' . $this->linkAttrs($var) . '>' . $this->text($this->index($var, 'title')) . '</a>'));
                return;
            case FieldType::IMAGE:
                $this->image($var, $get, $f->getReturnFormat());
                return;
            case FieldType::FILE:
                $this->assign($var, $get);
                $url = match ($f->getReturnFormat()) {
                    'url'   => $var,
                    'array' => $this->index($var, 'url'),
                    default => $this->call('wp_get_attachment_url', [$var]),
                };
                $this->ifSet($var, fn () => $this->line('<a href="' . $this->url($url) . '" download>' . $this->label($f) . '</a>'));
                return;
            case FieldType::GALLERY:
                $this->loop($var, $get, 'image_id', fn () => $this->line($this->raw($this->call('wp_get_attachment_image', ['image_id', "'large'"]))));
                return;
            case FieldType::TRUE_FALSE:
                $this->ifSet($get, fn () => $this->comment($this->label($f) . ' is on'));
                return;
            case FieldType::CHECKBOX:
                $this->loop($var, $get, 'choice', fn () => $this->line('<span>' . $this->text('choice') . '</span>'));
                return;
            case FieldType::OEMBED:
                $this->html($this->call('wp_oembed_get', [$get]), 'div');
                return;
            case FieldType::ICON:
                $this->line('<span class="' . $this->attr($get) . '" aria-hidden="true"></span>');
                return;
            case FieldType::CODE:
                $this->line('<pre><code>' . $this->text($get) . '</code></pre>');
                return;
            case FieldType::MAP:
                $this->assign($var, $get);
                $this->ifSet($var, fn () => $this->line('<div data-lat="' . $this->attr($this->index($var, 'lat')) . '" data-lng="' . $this->attr($this->index($var, 'lng')) . '"></div>'));
                return;
            case FieldType::POST_OBJECT:
            case FieldType::RELATIONSHIP:
            case FieldType::PAGE_LINK:
                $this->related($f, $var, $get, 'post');
                return;
            case FieldType::USER:
                $this->related($f, $var, $get, 'user');
                return;
            case FieldType::TAXONOMY_TERM:
                $this->related($f, $var, $get, 'term');
                return;
            case FieldType::GROUP:
                $this->assign($var, $get);
                $this->nested($f, $var);
                return;
            case FieldType::REPEATER:
                $this->repeater($f, $var, $get, $row);
                return;
            case FieldType::FLEXIBLE_CONTENT:
                $this->flexible($f, $var, $get, $row);
                return;
            case FieldType::CLONE:
                $this->comment($key . ': clone — its fields are part of this group');
                return;
            case FieldType::SELECT:
                if ($this->isMultiple($f)) {
                    $this->loop($var, $get, 'choice', fn () => $this->line('<span>' . $this->text('choice') . '</span>'));
                    return;
                }
                // one value: printed as text below
            default:
                $this->line('<p>' . $this->text($get) . '</p>');
        }
    }

    // -------------------------------------------------------------------------
    // Field shapes
    // -------------------------------------------------------------------------

    private function image(string $var, string $get, string $format): void
    {
        $this->assign($var, $get);
        $tag = match ($format) {
            'url'   => '<img src="' . $this->url($var) . '" alt="">',
            'array' => '<img src="' . $this->url($this->index($var, 'url')) . '" alt="' . $this->attr($this->index($var, 'alt')) . '">',
            default => $this->raw($this->call('wp_get_attachment_image', [$var, "'large'"])),
        };
        $this->ifSet($var, fn () => $this->line($tag));
    }

    private function related(FieldDefinition $f, string $var, string $get, string $kind): void
    {
        [$title, $link] = match ($kind) {
            'user'  => [$this->call('get_the_author_meta', ["'display_name'", 'item_id']), $this->call('get_author_posts_url', ['item_id'])],
            'term'  => [$this->call('get_term_field', ["'name'", 'item_id']), $this->call('get_term_link', ['item_id'])],
            default => [$this->call('get_the_title', ['item_id']), $this->call('get_permalink', ['item_id'])],
        };
        $print = fn () => $this->line('<a href="' . $this->url($link) . '">' . $this->text($title) . '</a>');

        if ($f->getType() === FieldType::RELATIONSHIP || $this->isMultiple($f)) {
            $this->comment($f->getKey() . ': IDs (with a return format "object", use the objects instead)');
            $this->loop($var, $get, 'item_id', $print);
            return;
        }
        $this->assign('item_id', $get);
        $this->ifSet('item_id', $print);
    }

    /** Sub-fields of a group, read from its array. */
    private function nested(FieldDefinition $f, string $var): void
    {
        if (! $f instanceof NestedFieldInterface) {
            return;
        }
        $this->ifSet($var, function () use ($f, $var): void {
            foreach ($f->getFields() as $sub) {
                $this->field($sub, $var);
            }
        });
    }

    private function repeater(FieldDefinition $f, string $var, string $get, ?string $row): void
    {
        $subs = $f instanceof NestedFieldInterface ? $f->getFields() : [];
        $item = $this->rowVar($f->getKey());

        // ACF loops (have_rows) work at the top level and inside another have_rows
        // row; inside a group's array, the rows are read from that array.
        if ($this->dialect === 'acf' && ($row === null || $row === 'row')) {
            $rows = $this->acfRows($f->getKey(), $row);
            $this->block("if ({$rows}):", 'endif;', function () use ($subs, $rows): void {
                $this->block("while ({$rows}): the_row();", 'endwhile;', function () use ($subs): void {
                    foreach ($subs as $sub) {
                        $this->field($sub, 'row');
                    }
                }, true);
            }, true);
            return;
        }

        $this->loop($var, $get, $item, function () use ($subs, $item): void {
            foreach ($subs as $sub) {
                $this->field($sub, $item);
            }
        });
    }

    private function flexible(FieldDefinition $f, string $var, string $get, ?string $row): void
    {
        if (! $f instanceof FlexibleContentInterface) {
            return;
        }
        $layouts = [];
        foreach ($f->getLayoutKeys() as $layout) {
            if ($layout !== 'ctrlf_global_block') { // expanded into its sections when read
                $layouts[$layout] = array_values($f->getLayoutFields($layout));
            }
        }

        // ACF loops (have_rows) work at the top level and inside another have_rows
        // row; inside a group's array, the rows are read from that array.
        if ($this->dialect === 'acf' && ($row === null || $row === 'row')) {
            $rows = $this->acfRows($f->getKey(), $row);
            $this->block("if ({$rows}):", 'endif;', function () use ($layouts, $rows): void {
                $this->block("while ({$rows}): the_row();", 'endwhile;', function () use ($layouts): void {
                    $first = true;
                    foreach ($layouts as $layout => $fields) {
                        $this->line(($first ? 'if' : 'elseif') . " (get_row_layout() === '{$layout}'):", true);
                        $first = false;
                        $this->depth++;
                        foreach ($fields as $sub) {
                            $this->field($sub, 'row');
                        }
                        $this->depth--;
                    }
                    if (! $first) {
                        $this->line('endif;', true);
                    }
                }, true);
            }, true);
            return;
        }

        $section = 'section';
        $this->loop($var, $get, $section, function () use ($layouts, $section): void {
            $first = true;
            foreach ($layouts as $layout => $fields) {
                $test = $this->index($section, '_layout') . " === '{$layout}'";
                $this->line(match ($this->dialect) {
                    'blade' => ($first ? '@if' : '@elseif') . " ({$test})",
                    'twig'  => ($first ? '{% if ' : '{% elseif ') . $this->index($section, '_layout') . " == '{$layout}' %}",
                    default => '<?php ' . ($first ? 'if' : 'elseif') . " ({$test}): ?>",
                });
                $first = false;
                $this->depth++;
                foreach ($fields as $sub) {
                    $this->field($sub, $section);
                }
                $this->depth--;
            }
            if (! $first) {
                $this->line(match ($this->dialect) {
                    'blade' => '@endif',
                    'twig'  => '{% endif %}',
                    default => '<?php endif; ?>',
                });
            }
        });
    }

    // -------------------------------------------------------------------------
    // Dialect primitives
    // -------------------------------------------------------------------------

    /** Reading a value: top level, or a key of the row / group array. */
    private function get(string $key, ?string $row): string
    {
        if ($row !== null) {
            if ($this->dialect === 'acf' && $row === 'row') {
                return 'get_sub_field(' . $this->lit($key) . ')';
            }
            return $this->index($row, $key);
        }

        $k = $this->lit($key);
        if ($this->dialect === 'twig') {
            return match ($this->entity['type']) {
                'term'    => "ctrlf_term({$k}, term.id)",
                'user'    => "ctrlf_user({$k}, user.id)",
                'options' => "function('ctrlfield_get_options', {$k}, " . $this->lit($this->entity['page'] ?? '') . ')',
                default   => "ctrlf({$k})",
            };
        }
        if ($this->dialect === 'acf') {
            return match ($this->entity['type']) {
                'term'    => "get_field({$k}, \$term)",
                'user'    => "get_field({$k}, 'user_' . \$user_id)",
                'options' => "get_field({$k}, 'option')",
                default   => "get_field({$k})",
            };
        }

        return match ($this->entity['type']) {
            'term'    => "ctrlfield_get_term({$k}, \$term->term_id)",
            'user'    => "ctrlfield_get_user({$k}, \$user_id)",
            'options' => "ctrlfield_get_options({$k}, " . $this->lit($this->entity['page'] ?? '') . ')',
            default   => "ctrlfield_get({$k})",
        };
    }

    /** have_rows() call: the post / term / user / option id only at the top level. */
    private function acfRows(string $key, ?string $row): string
    {
        $id = $row !== null ? '' : match ($this->entity['type']) {
            'term'    => ', $term',
            'user'    => ", 'user_' . \$user_id",
            'options' => ", 'option'",
            default   => '',
        };

        return 'have_rows(' . $this->lit($key) . $id . ')';
    }

    private function index(string $var, string $key): string
    {
        return $this->dialect === 'twig'
            ? $this->twigVar($var) . '.' . $key
            : $this->phpVar($var) . "['{$key}']";
    }

    private function assign(string $var, string $expr): void
    {
        $this->line(match ($this->dialect) {
            'blade' => "@php(\${$var} = {$expr})",
            'twig'  => "{% set {$var} = {$expr} %}",
            default => "<?php \${$var} = {$expr}; ?>",
        });
    }

    private function ifSet(string $expr, callable $body): void
    {
        $e = $this->expr($expr);
        match ($this->dialect) {
            'blade' => $this->block("@if (!empty({$e}))", '@endif', $body),
            'twig'  => $this->block("{% if {$e} %}", '{% endif %}', $body),
            default => $this->block("<?php if (!empty({$e})): ?>", '<?php endif; ?>', $body),
        };
    }

    private function loop(string $var, string $get, string $item, callable $body): void
    {
        if ($this->dialect === 'twig') {
            $this->block("{% for {$item} in ({$get})|default([]) %}", '{% endfor %}', $body);
            return;
        }
        // A key of a row / group array may be missing: ?? avoids the warning.
        $list = str_contains($get, "['") && ! str_contains($get, '(') ? "{$get} ?? []" : "{$get} ?: []";
        if ($this->dialect === 'blade') {
            $this->block("@foreach ({$list} as \${$item})", '@endforeach', $body);
            return;
        }
        $this->block("<?php foreach ({$list} as \${$item}): ?>", '<?php endforeach; ?>', $body);
    }

    /** Escaped text. */
    private function text(string $expr): string
    {
        $e = $this->expr($expr);
        return match ($this->dialect) {
            'blade' => "{{ {$e} }}",
            'twig'  => "{{ {$e}|e }}", // Timber does not escape by default
            default => "<?= esc_html({$e}) ?>",
        };
    }

    private function attr(string $expr): string
    {
        $e = $this->expr($expr);
        return match ($this->dialect) {
            'blade' => "{{ {$e} }}",
            'twig'  => "{{ {$e}|e('html_attr') }}",
            default => "<?= esc_attr({$e}) ?>",
        };
    }

    private function url(string $expr): string
    {
        $e = $this->expr($expr);
        return match ($this->dialect) {
            'blade' => "{{ esc_url({$e}) }}",
            'twig'  => "{{ function('esc_url', {$e})|raw }}",
            default => "<?= esc_url({$e}) ?>",
        };
    }

    /** HTML that is already safe (a WordPress function's output). */
    private function raw(string $expr): string
    {
        $e = $this->expr($expr);
        return match ($this->dialect) {
            'blade' => "{!! {$e} !!}",
            'twig'  => "{{ {$e}|raw }}",
            default => "<?= {$e} ?>",
        };
    }

    /** Rich text: passed through wp_kses_post, inside a wrapper. */
    private function html(string $expr, string $tag): void
    {
        $e = $this->expr($expr);
        $this->line("<{$tag}>" . match ($this->dialect) {
            'blade' => "{!! wp_kses_post({$e}) !!}",
            'twig'  => "{{ function('wp_kses_post', {$e})|raw }}",
            default => "<?= wp_kses_post({$e}) ?>",
        } . "</{$tag}>");
    }

    private function textarea(string $expr): void
    {
        $e = $this->expr($expr);
        $this->line('<p>' . match ($this->dialect) {
            'blade' => "{!! nl2br(e({$e})) !!}",
            'twig'  => "{{ {$e}|e|nl2br }}",
            default => "<?= nl2br(esc_html({$e})) ?>",
        } . '</p>');
    }

    private function linkAttrs(string $var): string
    {
        $v = $this->expr($var);
        return match ($this->dialect) {
            'blade' => "{!! ctrlfield_link_attrs({$v}) !!}",
            'twig'  => "{{ function('ctrlfield_link_attrs', {$v})|raw }}",
            default => "<?= ctrlfield_link_attrs({$v}) ?>",
        };
    }

    /** @param list<string> $args variable names or literal expressions */
    private function call(string $fn, array $args): string
    {
        $args = array_map(fn (string $a): string => $this->isName($a) ? $this->expr($a) : $a, $args);
        return $this->dialect === 'twig'
            ? "function('{$fn}', " . implode(', ', $args) . ')'
            : $fn . '(' . implode(', ', $args) . ')';
    }

    /** A variable name becomes $name (PHP) or name (Twig); expressions pass through. */
    private function expr(string $e): string
    {
        if (! $this->isName($e)) {
            return $e;
        }
        return $this->dialect === 'twig' ? $e : '$' . $e;
    }

    private function isName(string $e): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $e);
    }

    private function phpVar(string $var): string
    {
        return str_starts_with($var, '$') ? $var : '$' . $var;
    }

    private function twigVar(string $var): string
    {
        return ltrim($var, '$');
    }

    private function lit(string $s): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";
    }

    private function label(FieldDefinition $f): string
    {
        $label = (string) ($f->getDefinition()['label'] ?? '');
        return htmlspecialchars($label !== '' ? $label : $f->getKey(), ENT_QUOTES, 'UTF-8');
    }

    private function varName(string $key): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $key);
        return preg_match('/^[0-9]/', $name) ? 'f_' . $name : $name;
    }

    private function rowVar(string $key): string
    {
        return $this->varName($key) . '_row';
    }

    private function isMultiple(FieldDefinition $f): bool
    {
        return method_exists($f, 'isMultiple') && (bool) $f->isMultiple();
    }

    private function comment(string $text): void
    {
        $text = str_replace(['*/', '--}}', '#}'], '', $text);
        $this->line(match ($this->dialect) {
            'blade' => "{{-- {$text} --}}",
            'twig'  => "{# {$text} #}",
            default => "<?php // {$text} ?>",
        });
    }

    /**
     * @param bool $phpBlock the open / close lines are bare PHP statements (acf), wrapped in tags
     */
    private function block(string $open, string $close, callable $body, bool $phpBlock = false): void
    {
        $this->line($open, $phpBlock);
        $this->depth++;
        $body();
        $this->depth--;
        $this->line($close, $phpBlock);
    }

    private function line(string $text, bool $php = false): void
    {
        $this->lines[] = str_repeat('    ', $this->depth) . ($php ? "<?php {$text} ?>" : $text);
    }
}
