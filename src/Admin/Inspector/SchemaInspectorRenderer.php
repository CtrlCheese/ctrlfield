<?php

declare(strict_types=1);

namespace CtrlField\Admin\Inspector;

use CtrlField\Admin\FieldGroups\FieldGroupsPage;
use CtrlField\Admin\FieldGroups\FieldGroupRepository;
use CtrlField\Admin\FieldGroups\FieldGroupsServiceProvider;
use CtrlField\Admin\FieldGroups\JsonFolderGuard;
use CtrlField\Builder\FieldGroup;
use CtrlField\Components\ComponentRegistry;
use CtrlField\Data\FieldDataService;
use CtrlField\Fields\Renderers\MessageRenderer;
use CtrlField\Storage\PostMetaAdapter;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\SchemaErrors;

/**
 * The Schema Inspector page: what is registered, where it shows, how much it
 * is used, and what is wrong with it.
 *
 *   Overview       summary, search / filters, one card per group with its whole field tree,
 *                  how often each field is filled in, and PHP / TypeScript types for templates
 *   Post values    one post's stored values next to what ctrlfield_get() returns
 *   Components     what ComponentRegistry discovered: files, screenshot, fields, the posts
 *                  using each one and its problems (ComponentReport)
 *   Health         problems of the schema and of the stored data, with their fixes
 *   Import/Export  CSV per post type
 *
 * Excluded from PHPStan — WP admin functions and $wpdb (through InspectorData).
 */
final class SchemaInspectorRenderer
{
    private const TABS = ['overview', 'values', 'components', 'health', 'io'];

    /** @var array<string, FieldGroup> */
    private array $groups;

    private SchemaAnalyzer $analyzer;

    private InspectorData $data;

    /** @var array<string, array{total: int, keys: array<string, int>}> post type => usage, read once */
    private array $usage = [];

    public function __construct()
    {
        $this->groups   = FieldRegistry::all();
        $acf            = class_exists(\CtrlField\Compat\Acf\AcfApi::class) ? \CtrlField\Compat\Acf\AcfApi::registeredKeys() : [];
        $this->analyzer = new SchemaAnalyzer($this->groups, FieldGroupsServiceProvider::registered(), $acf);
        $this->data     = new InspectorData();
    }

    public function render(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show
        $tab = in_array($tab, self::TABS, true) ? $tab : 'overview';

        $issues   = $this->analyzer->issues();
        $schema   = SchemaErrors::all();
        $stale    = $this->data->stalePosts();
        $orphans  = $this->data->orphans($this->groups);
        $exposed  = $this->jsonExposure();
        $problems = count($schema) + count(array_filter($issues, static fn (array $i): bool => $i['level'] !== 'info'))
            + ($stale !== [] ? 1 : 0) + count($orphans) + ($exposed !== null ? 1 : 0);

        echo '<div class="wrap ctrlf-insp">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Schema Inspector', 'ctrlfield') . '</h1>';
        echo '<p class="ctrlf-insp__lead">' . esc_html__('Every field group CtrlField knows, where it shows, how much it is used — and what needs attention.', 'ctrlfield') . '</p>';

        $labels = [
            'overview'   => __('Overview', 'ctrlfield'),
            'values'     => __('Post values', 'ctrlfield'),
            'components' => __('Components', 'ctrlfield'),
            /* translators: %d: number of problems */
            'health'   => $problems > 0 ? sprintf(__('Health (%d)', 'ctrlfield'), $problems) : __('Health', 'ctrlfield'),
            'io'       => __('Import / Export', 'ctrlfield'),
        ];
        echo '<nav class="nav-tab-wrapper ctrlf-insp__tabs">';
        foreach ($labels as $key => $label) {
            printf(
                '<a href="%s" class="nav-tab%s%s">%s</a>',
                esc_url(add_query_arg(['page' => 'ctrlfield', 'tab' => $key], admin_url('admin.php'))),
                $key === $tab ? ' nav-tab-active' : '',
                $key === 'health' && $problems > 0 ? ' ctrlf-insp__tab--alert' : '',
                esc_html($label)
            );
        }
        echo '</nav>';

        match ($tab) {
            'health'     => $this->health($issues, $schema, $stale, $orphans, $exposed),
            'io'         => $this->importExport(),
            'values'     => $this->values(),
            'components' => $this->components($schema, $issues),
            default  => $this->overview($problems),
        };

        echo '</div>';
    }

    // ── Overview ─────────────────────────────────────────────────────────────

    private function overview(int $problems): void
    {
        $fieldCount = 0;
        $trees      = [];
        foreach ($this->groups as $key => $group) {
            $trees[$key] = SchemaAnalyzer::tree(array_values($group->getFields()));
            $fieldCount += SchemaAnalyzer::count($trees[$key]);
        }

        $healthUrl = esc_url(add_query_arg(['page' => 'ctrlfield', 'tab' => 'health'], admin_url('admin.php')));
        $cards     = [
            [__('Field groups', 'ctrlfield'), count($this->groups), '', false],
            [__('Fields', 'ctrlfield'), $fieldCount, __('sub-fields and layouts included', 'ctrlfield'), false],
            [__('Posts with data', 'ctrlfield'), $this->data->postsWithData(), '', false],
            [__('Problems', 'ctrlfield'), $problems, $problems > 0 ? __('see Health', 'ctrlfield') : __('nothing to fix', 'ctrlfield'), true],
        ];
        echo '<div class="ctrlf-insp__cards">';
        foreach ($cards as [$label, $value, $note, $isProblems]) {
            $inner = '<span class="ctrlf-insp__card-value">' . esc_html(number_format_i18n((int) $value)) . '</span>'
                . '<span class="ctrlf-insp__card-label">' . esc_html($label) . '</span>'
                . ($note !== '' ? '<span class="ctrlf-insp__card-note">' . esc_html($note) . '</span>' : '');
            echo $isProblems
                ? '<a href="' . $healthUrl . '" class="ctrlf-insp__card' . ($value > 0 ? ' is-alert' : ' is-ok') . '">' . $inner . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
                : '<div class="ctrlf-insp__card">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
        }
        echo '</div>';

        if ($this->groups === []) {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('No field groups yet. Create one in Field Groups, or define them in PHP.', 'ctrlfield') . '</p>';
            return;
        }

        $sources = ['code' => __('Code (PHP)', 'ctrlfield'), 'json' => __('Field Groups (JSON)', 'ctrlfield'), 'acf' => __('ACF', 'ctrlfield'), 'block' => __('Global blocks', 'ctrlfield')];
        $places  = ['post' => __('Posts and pages', 'ctrlfield'), 'options' => __('Options pages', 'ctrlfield'), 'term' => __('Terms', 'ctrlfield'), 'user' => __('Users', 'ctrlfield'), 'comment' => __('Comments', 'ctrlfield'), 'other' => __('Other', 'ctrlfield')];

        echo '<div class="ctrlf-insp__list" x-data="{ q: \'\', source: \'\', place: \'\', shown(el) { const q = this.q.trim().toLowerCase(); return (!q || el.dataset.search.includes(q)) && (!this.source || el.dataset.source === this.source) && (!this.place || el.dataset.place === this.place); } }">';
        echo '<div class="ctrlf-insp__filters">';
        printf('<input type="search" class="ctrlf-input" x-model="q" placeholder="%1$s" aria-label="%1$s">', esc_attr__('Search groups and fields (key or label)…', 'ctrlfield'));
        $this->select('source', __('All origins', 'ctrlfield'), $sources);
        $this->select('place', __('All places', 'ctrlfield'), $places);
        echo '</div>';

        foreach ($this->groups as $key => $group) {
            $this->groupCard((string) $key, $group, $trees[$key], $sources, $places);
        }
        echo '<p class="ctrlf-insp__empty" x-show="![...$el.parentElement.querySelectorAll(\'.ctrlf-insp-group\')].some((g) => shown(g))">' . esc_html__('No group matches.', 'ctrlfield') . '</p>';
        echo '</div>';
    }

    /** @param array<string, string> $choices */
    private function select(string $model, string $all, array $choices): void
    {
        echo '<select class="ctrlf-input" x-model="' . esc_attr($model) . '"><option value="">' . esc_html($all) . '</option>';
        foreach ($choices as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @param list<array<string, mixed>> $tree
     * @param array<string, string>      $sources
     * @param array<string, string>      $places
     */
    private function groupCard(string $key, FieldGroup $group, array $tree, array $sources, array $places): void
    {
        $source = $this->analyzer->source($key, $group);
        $place  = SchemaAnalyzer::place($group);
        $types  = SchemaAnalyzer::postTypes($group);
        $count  = SchemaAnalyzer::count($tree);
        $search = strtolower($key . ' ' . $group->getTitle() . ' ' . $this->searchText($tree));
        $where  = SchemaAnalyzer::where($group, static function (string $type): string {
            $object = get_post_type_object($type);
            return $object !== null ? (string) $object->labels->singular_name : $type;
        });

        printf(
            '<details class="ctrlf-insp-group" data-search="%s" data-source="%s" data-place="%s" x-show="shown($el)">',
            esc_attr($search),
            esc_attr($source),
            esc_attr($place)
        );
        echo '<summary class="ctrlf-insp-group__head">';
        echo '<span class="ctrlf-insp-group__title"><strong>' . esc_html($group->getTitle() !== '' ? $group->getTitle() : $key) . '</strong>'
            . ' <code>' . esc_html($key) . '</code></span>';
        echo '<span class="ctrlf-insp-group__meta">'
            . '<span class="ctrlf-badge ctrlf-badge--' . esc_attr($source) . '">' . esc_html($sources[$source] ?? $source) . '</span>'
            . '<span class="ctrlf-badge">' . esc_html($places[$place] ?? $place) . '</span>'
            /* translators: %d: number of fields */
            . '<span class="ctrlf-insp-group__count">' . esc_html(sprintf(_n('%d field', '%d fields', $count, 'ctrlfield'), $count)) . '</span>'
            . '</span>';
        echo '<span class="ctrlf-insp-group__where">' . esc_html($where) . '</span>';

        // Coverage: posts of each type that have CtrlField data.
        foreach ($types as $type) {
            [$with, $total] = $this->data->coverage($type);
            $pct    = $total > 0 ? (int) round($with / $total * 100) : 0;
            $object = get_post_type_object($type);
            printf(
                '<span class="ctrlf-insp-cover" title="%4$s"><span class="ctrlf-insp-cover__label">%1$s</span><span class="ctrlf-insp-cover__bar"><span style="width:%2$d%%"></span></span><span class="ctrlf-insp-cover__value">%3$s</span></span>',
                esc_html($object !== null ? (string) $object->labels->name : $type),
                (int) $pct,
                esc_html(sprintf('%d / %d', $with, $total)),
                /* translators: 1: posts with data, 2: published posts, 3: post type */
                esc_attr(sprintf(__('%1$d of %2$d published %3$s have CtrlField data', 'ctrlfield'), $with, $total, $type))
            );
        }
        echo '</summary>';

        $usage = null;
        foreach ($types as $type) {
            $this->usage[$type] ??= $this->data->usage($type);
            $usage ??= ['total' => 0, 'keys' => []];
            $usage['total'] += $this->usage[$type]['total'];
            foreach ($this->usage[$type]['keys'] as $k => $n) {
                $usage['keys'][$k] = ($usage['keys'][$k] ?? 0) + $n;
            }
        }

        echo '<div class="ctrlf-insp-group__body" x-data="{ types: false, lang: \'php\' }"><p class="ctrlf-insp-group__actions">';
        if ($source === 'json') {
            printf('<a class="button button-small" href="%s">%s</a> ', esc_url($this->fieldGroupsUrl(['action' => 'edit', 'group' => $key])), esc_html__('Edit', 'ctrlfield'));
            printf('<a class="button button-small" href="%s">%s</a> ', esc_url($this->fieldGroupsUrl(['action' => 'code', 'group' => $key])), esc_html__('Export PHP', 'ctrlfield'));
        }
        printf('<a class="button button-small" href="%s">%s</a> ', esc_url($this->fieldGroupsUrl(['action' => 'usage', 'group' => $key])), esc_html__('Template code', 'ctrlfield'));
        printf('<button type="button" class="button button-small" :class="{ \'is-active\': types }" :aria-expanded="types" @click="types = !types">%s</button>', esc_html__('Types (PHP / TypeScript)', 'ctrlfield'));
        echo '</p>';
        $this->typesPanel($group);

        echo '<table class="ctrlf-insp-fields"><thead><tr>'
            . '<th>' . esc_html__('Field', 'ctrlfield') . '</th>'
            . '<th>' . esc_html__('Key', 'ctrlfield') . '</th>'
            . '<th>' . esc_html__('Type', 'ctrlfield') . '</th>'
            . '<th>' . esc_html__('Settings', 'ctrlfield') . '</th>'
            . ($usage !== null ? '<th title="' . esc_attr__('Posts with data where the field has a value', 'ctrlfield') . '">' . esc_html__('Filled', 'ctrlfield') . '</th>' : '')
            . '</tr></thead><tbody>';
        $this->rows($tree, 0, $usage);
        echo '</tbody></table></div></details>';
    }

    /**
     * @param list<array<string, mixed>>                          $tree
     * @param array{total: int, keys: array<string, int>}|null $usage top-level fields only
     */
    private function rows(array $tree, int $depth, ?array $usage = null): void
    {
        $copy = esc_attr__('Copy the key', 'ctrlfield');
        foreach ($tree as $node) {
            $flags = array_filter([
                $node['required'] ? __('required', 'ctrlfield') : '',
                $node['width'] !== null ? $node['width'] . '%' : '',
                /* translators: %s: return format */
                $node['return'] !== '' ? sprintf(__('returns %s', 'ctrlfield'), $node['return']) : '',
                (string) $node['condition'],
            ]);
            printf(
                '<tr class="%1$s"><td style="--depth:%2$d"><span class="ctrlf-insp-fields__label">%3$s</span></td>'
                . '<td><code>%4$s</code> <button type="button" class="ctrlf-insp-copy" title="%5$s" aria-label="%5$s" data-copy="%4$s"'
                . ' @click="navigator.clipboard.writeText($el.dataset.copy); $el.classList.add(\'is-done\'); setTimeout(() => $el.classList.remove(\'is-done\'), 1200)">'
                . '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><rect x="6.5" y="6.5" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M13.5 4.5V4a1.5 1.5 0 0 0-1.5-1.5H4A1.5 1.5 0 0 0 2.5 4v8A1.5 1.5 0 0 0 4 13.5h.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>'
                . '</button></td><td><span class="ctrlf-badge">%6$s</span></td><td class="ctrlf-insp-fields__flags">%7$s</td>%8$s</tr>',
                $node['ui'] ? 'is-ui' : '',
                (int) $depth,
                esc_html($node['label'] !== '' ? $node['label'] : '—'),
                esc_html($node['key']),
                $copy, // phpcs:ignore WordPress.Security.EscapeOutput -- esc_attr__()
                esc_html($node['type']),
                esc_html(implode(' · ', $flags)),
                $usage !== null ? '<td>' . $this->filledCell($node, $depth, $usage) . '</td>' : '' // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
            );
            $this->rows($node['children'], $depth + 1, $usage !== null ? ['total' => 0, 'keys' => []] : null);
            foreach ($node['layouts'] as $layout) {
                printf(
                    '<tr class="is-layout"><td style="--depth:%1$d" colspan="%4$d"><span class="ctrlf-badge ctrlf-badge--layout">%2$s</span> %3$s</td></tr>',
                    (int) ($depth + 1),
                    esc_html__('Layout', 'ctrlfield'),
                    esc_html(($layout['label'] !== '' ? $layout['label'] . ' · ' : '') . $layout['key']),
                    $usage !== null ? 5 : 4
                );
                $this->rows($layout['fields'], $depth + 2, $usage !== null ? ['total' => 0, 'keys' => []] : null);
            }
        }
    }

    /**
     * "12 / 15" with a bar, for a top-level field that stores a value.
     *
     * @param array<string, mixed>                          $node
     * @param array{total: int, keys: array<string, int>} $usage
     */
    private function filledCell(array $node, int $depth, array $usage): string
    {
        if ($depth > 0 || $node['ui'] || $usage['total'] === 0) {
            return '';
        }
        $n   = $usage['keys'][$node['key']] ?? 0;
        $pct = (int) round($n / $usage['total'] * 100);

        return sprintf(
            '<span class="ctrlf-insp-filled%4$s" title="%3$s"><span class="ctrlf-insp-cover__bar"><span style="width:%1$d%%"></span></span>%2$s</span>',
            $pct,
            esc_html($n . ' / ' . $usage['total']),
            /* translators: 1: posts with a value, 2: posts with data */
            esc_attr(sprintf(__('%1$d of %2$d posts have a value', 'ctrlfield'), $n, $usage['total'])),
            $n === 0 ? ' is-empty' : ''
        );
    }

    /** The group as a PHP array shape and a TypeScript interface, for editors' autocompletion. */
    private function typesPanel(FieldGroup $group): void
    {
        $types = new TypesGenerator();
        echo '<div class="ctrlf-insp-types" x-show="types" x-cloak>';
        echo '<div class="ctrlf-insp-types__bar"><div class="ctrlf-insp-types__tabs" role="tablist">'
            . '<button type="button" role="tab" :aria-selected="lang === \'php\'" @click="lang = \'php\'">PHP</button>'
            . '<button type="button" role="tab" :aria-selected="lang === \'ts\'" @click="lang = \'ts\'">TypeScript</button></div>'
            . '<span class="ctrlf-insp-types__hint" x-show="lang === \'php\'">' . esc_html__('Put it above the template code — PhpStorm, PHPStan and Intelephense complete the keys.', 'ctrlfield') . '</span>'
            . '<button type="button" class="button button-small" @click="navigator.clipboard.writeText($el.closest(\'.ctrlf-insp-types\').querySelector(\'pre:not([style*=none]) code\').textContent); $el.textContent = ' . esc_attr(\CtrlField\Admin\Js::json(__('Copied', 'ctrlfield'))) . '">' . esc_html__('Copy', 'ctrlfield') . '</button></div>';
        echo '<pre x-show="lang === \'php\'"><code>' . esc_html($types->php($group)) . '</code></pre>';
        echo '<pre x-show="lang === \'ts\'"><code>' . esc_html($types->typescript($group)) . '</code></pre>';
        echo '</div>';
    }

    /** @param list<array<string, mixed>> $tree */
    private function searchText(array $tree): string
    {
        $parts = [];
        foreach ($tree as $node) {
            $parts[] = $node['key'] . ' ' . $node['label'];
            $parts[] = $this->searchText($node['children']);
            foreach ($node['layouts'] as $layout) {
                $parts[] = $layout['key'] . ' ' . $layout['label'] . ' ' . $this->searchText($layout['fields']);
            }
        }

        return implode(' ', $parts);
    }

    /** @param array<string, string> $args */
    private function fieldGroupsUrl(array $args): string
    {
        return add_query_arg(['page' => FieldGroupsPage::SLUG] + $args, admin_url('admin.php'));
    }

    // ── Post values ──────────────────────────────────────────────────────────

    /**
     * One post: what is stored, and what ctrlfield_get() hands templates
     * (return formats applied, links resolved, global blocks expanded).
     */
    private function values(): void
    {
        $postId = isset($_GET['post']) ? absint(wp_unslash((string) $_GET['post'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which post to show
        $types  = [];
        foreach ($this->groups as $group) {
            $types = [...$types, ...SchemaAnalyzer::postTypes($group)];
        }
        $types = array_values(array_unique($types));

        echo '<p class="ctrlf-insp__lead">' . esc_html__('Pick a post to see what is stored for it next to what ctrlfield_get() returns in templates.', 'ctrlfield') . '</p>';
        printf(
            '<div class="ctrlf-insp-post" x-data="ctrlfInspectorPost(%s)">'
            . '<input type="search" class="ctrlf-input" x-model="q" @input.debounce.250ms="search()" @focus="open = true; if (!results.length) search()" @keydown.escape="open = false" placeholder="%s" aria-label="%2$s">'
            . '<ul class="ctrlf-insp-post__results" x-show="open && (results.length || loading)" @click.outside="open = false">'
            . '<li x-show="loading" class="is-muted">%s</li>'
            . '<template x-for="item in results" :key="item.id"><li><a :href="link(item.id)"><strong x-text="item.title"></strong> <span x-text="item.meta"></span><span class="ctrlf-insp-post__date" x-text="item.date"></span></a></li></template>'
            . '</ul></div>',
            esc_attr(\CtrlField\Admin\Js::json([
                'source' => 'post:' . implode(',', $types !== [] ? $types : ['post', 'page']),
                'url'    => add_query_arg(['page' => 'ctrlfield', 'tab' => 'values'], admin_url('admin.php')),
            ])),
            esc_attr__('Search posts…', 'ctrlfield'),
            esc_html__('Searching…', 'ctrlfield')
        );

        $post = $postId > 0 ? get_post($postId) : null;
        if ($post === null) {
            return;
        }
        if (! current_user_can('edit_post', $post->ID)) {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('You cannot edit this post.', 'ctrlfield') . '</p>';
            return;
        }

        $raw    = (new PostMetaAdapter(new WpPostMetaDriver()))->loadRaw($post->ID);
        $stored = $raw['fields'] ?? [];
        $object = get_post_type_object($post->post_type);
        printf(
            '<div class="ctrlf-insp-post__head"><strong>%s</strong> <span class="ctrlf-badge">%s</span> <code>#%d</code> %s <a class="button button-small" href="%s">%s</a></div>',
            esc_html($post->post_title !== '' ? $post->post_title : __('(no title)', 'ctrlfield')),
            esc_html($object !== null ? (string) $object->labels->singular_name : $post->post_type),
            (int) $post->ID,
            $raw !== null
                /* translators: %d: schema version */
                ? '<span class="ctrlf-insp-post__version">' . esc_html(sprintf(__('schema version %d', 'ctrlfield'), $raw['schema_version'])) . '</span>'
                : '',
            esc_url((string) get_edit_post_link($post->ID)),
            esc_html__('Edit', 'ctrlfield')
        );

        if ($raw === null) {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('Nothing is stored for this post yet.', 'ctrlfield') . '</p>';
            return;
        }

        $service = FieldDataService::getInstance();
        $shown   = [];
        foreach ($this->groups as $key => $group) {
            $applies = in_array($post->post_type, SchemaAnalyzer::postTypes($group), true);
            $fields  = array_values(array_filter($group->getFields(), static fn ($f): bool => ! $f->isUiOnly()));
            $hasData = array_filter($fields, static fn ($f): bool => array_key_exists($f->getKey(), $stored)) !== [];
            if ($fields === [] || (! $applies && ! $hasData)) {
                continue;
            }
            $rows = '';
            foreach ($fields as $field) {
                $k         = $field->getKey();
                $shown[$k] = true;
                $isStored  = array_key_exists($k, $stored);
                $resolved  = $isStored ? $service->get($k, $post->ID) : null;
                $a         = self::preview($stored[$k] ?? null);
                $b         = self::preview($resolved);
                $rows     .= sprintf(
                    '<tr><td><span class="ctrlf-insp-fields__label">%s</span><code>%s</code> <span class="ctrlf-badge">%s</span></td><td>%s</td><td>%s%s</td></tr>',
                    esc_html((string) ($field->getDefinition()['label'] ?? '') !== '' ? (string) $field->getDefinition()['label'] : $k),
                    esc_html($k),
                    esc_html($field->getType()->value),
                    $isStored ? '<pre class="ctrlf-insp-value">' . esc_html($a) . '</pre>' : '<span class="ctrlf-insp-value is-missing">' . esc_html__('not stored', 'ctrlfield') . '</span>',
                    $isStored ? '<pre class="ctrlf-insp-value">' . esc_html($b) . '</pre>' : '',
                    $isStored && $a !== $b ? '<span class="ctrlf-badge ctrlf-badge--layout">' . esc_html__('transformed', 'ctrlfield') . '</span>' : ''
                );
            }
            $this->valuesTable($group->getTitle() !== '' ? $group->getTitle() : (string) $key, (string) $key, $rows);
        }

        $left = array_diff_key($stored, $shown);
        if ($left !== []) {
            $rows = '';
            foreach ($left as $k => $value) {
                $rows .= sprintf('<tr><td><code>%s</code></td><td><pre class="ctrlf-insp-value">%s</pre></td><td><span class="ctrlf-insp-value is-missing">%s</span></td></tr>', esc_html((string) $k), esc_html(self::preview($value)), esc_html__('no field — never shown', 'ctrlfield'));
            }
            $this->valuesTable(__('Stored without a field', 'ctrlfield'), '', $rows);
        }
    }

    private function valuesTable(string $title, string $key, string $rows): void
    {
        echo '<h3 class="ctrlf-insp-values__title">' . esc_html($title) . ($key !== '' ? ' <code>' . esc_html($key) . '</code>' : '') . '</h3>';
        echo '<table class="ctrlf-insp-fields ctrlf-insp-values"><thead><tr><th>' . esc_html__('Field', 'ctrlfield') . '</th><th>'
            . esc_html__('Stored', 'ctrlfield') . '</th><th>' . esc_html__('ctrlfield_get()', 'ctrlfield') . '</th></tr></thead><tbody>'
            . $rows . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }

    /** A value as readable text: JSON for arrays, a short description for objects. */
    public static function preview(mixed $value): string
    {
        $plain = self::plain($value);
        if (is_string($plain)) {
            $text = $plain === '' ? '""' : $plain;
        } else {
            $text = (string) json_encode($plain, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return mb_strlen($text) > 3000 ? mb_substr($text, 0, 3000) . "\n…" : $text;
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            is_array($value)                       => array_map([self::class, 'plain'], $value),
            $value instanceof \WP_Post            => sprintf('WP_Post #%d “%s”', $value->ID, $value->post_title),
            $value instanceof \WP_Term            => sprintf('WP_Term #%d “%s”', $value->term_id, $value->name),
            $value instanceof \WP_User            => sprintf('WP_User #%d “%s”', $value->ID, $value->display_name),
            $value instanceof \DateTimeInterface  => get_class($value) . ' ' . $value->format('Y-m-d H:i:s T'),
            is_object($value)                      => get_class($value),
            default                                => $value,
        };
    }

    // ── Components ───────────────────────────────────────────────────────────

    /**
     * @param array<string, string>       $schema file => error
     * @param list<array<string, string>> $issues SchemaAnalyzer::issues()
     */
    private function components(array $schema, array $issues): void
    {
        $report = (new ComponentReport($this->groups, $schema, $issues))->all();
        if ($report === []) {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('No components discovered. A theme registers them with ComponentRegistry::discover( get_template_directory() . \'/components\' ) on init.', 'ctrlfield') . '</p>';
            return;
        }

        $usedBy     = $this->data->layoutUsage();
        $categories = [];
        foreach ($report as $item) {
            $category = $item['component']->category;
            if ($category !== '') {
                $categories[$category] = ucfirst($category);
            }
        }
        ksort($categories);

        echo '<p class="ctrlf-insp__lead">' . esc_html__('Components found by ComponentRegistry::discover(): their files, their fields, the posts whose flexible content uses them, and what is wrong with them.', 'ctrlfield') . '</p>';
        echo '<div class="ctrlf-insp__list" x-data="{ q: \'\', category: \'\', state: \'\', shown(el) { const q = this.q.trim().toLowerCase(); return (!q || el.dataset.search.includes(q)) && (!this.category || el.dataset.category === this.category) && (!this.state || el.dataset.state.split(\' \').includes(this.state)); } }">';
        echo '<div class="ctrlf-insp__filters">';
        printf('<input type="search" class="ctrlf-input" x-model="q" placeholder="%1$s" aria-label="%1$s">', esc_attr__('Search components and fields (key or label)…', 'ctrlfield'));
        if ($categories !== []) {
            $this->select('category', __('All categories', 'ctrlfield'), $categories);
        }
        $this->select('state', __('All components', 'ctrlfield'), [
            'problem' => __('With problems', 'ctrlfield'),
            'unused'  => __('Not used', 'ctrlfield'),
        ]);
        echo '</div>';

        foreach ($report as $key => $item) {
            $this->componentCard($key, $item, $usedBy[$key] ?? []);
        }
        echo '<p class="ctrlf-insp__empty" x-show="![...$el.parentElement.querySelectorAll(\'.ctrlf-insp-group\')].some((g) => shown(g))">' . esc_html__('No component matches.', 'ctrlfield') . '</p>';
        echo '</div>';
    }

    /**
     * @param array{component: \CtrlField\Components\ComponentDefinition, files: list<string>, screenshot: string, fields: list<array<string, mixed>>, issues: list<array{level: string, message: string}>} $item
     * @param list<int> $posts
     */
    private function componentCard(string $key, array $item, array $posts): void
    {
        $c      = $item['component'];
        $errors = count(array_filter($item['issues'], static fn (array $i): bool => $i['level'] === 'error'));
        $warns  = count($item['issues']) - $errors;
        $count  = SchemaAnalyzer::count($item['fields']);
        $label  = $c->label !== '' ? $c->label : $key;
        $state  = array_filter([$item['issues'] !== [] ? 'problem' : '', $posts === [] ? 'unused' : '']);
        $thumb  = $item['screenshot'] !== '' && defined('WP_CONTENT_DIR') && str_starts_with($item['screenshot'], WP_CONTENT_DIR)
            ? content_url(substr($item['screenshot'], strlen(WP_CONTENT_DIR)))
            : '';

        printf(
            '<details class="ctrlf-insp-group ctrlf-insp-comp%s" data-search="%s" data-category="%s" data-state="%s" x-show="shown($el)">',
            $errors > 0 ? ' is-error' : '',
            esc_attr(strtolower($label . ' ' . $key . ' ' . $c->folder . ' ' . $c->description . ' ' . $this->searchText($item['fields']))),
            esc_attr($c->category),
            esc_attr(implode(' ', $state))
        );
        echo '<summary class="ctrlf-insp-group__head ctrlf-insp-comp__head">';
        echo $thumb !== ''
            ? '<img class="ctrlf-insp-comp__thumb" src="' . esc_url($thumb) . '" alt="" loading="lazy">'
            : '<span class="ctrlf-insp-comp__thumb is-empty" aria-hidden="true"></span>';
        echo '<span class="ctrlf-insp-comp__main"><span class="ctrlf-insp-group__title"><strong>' . esc_html($label) . '</strong> <code>' . esc_html($key) . '</code>';
        if ($c->category !== '') {
            echo ' <span class="ctrlf-badge">' . esc_html($c->category) . '</span>';
        }
        if ($errors > 0) {
            /* translators: %d: number of errors */
            echo ' <span class="ctrlf-badge ctrlf-badge--error">' . esc_html(sprintf(_n('%d error', '%d errors', $errors, 'ctrlfield'), $errors)) . '</span>';
        }
        if ($warns > 0) {
            /* translators: %d: number of warnings */
            echo ' <span class="ctrlf-badge ctrlf-badge--warning">' . esc_html(sprintf(_n('%d warning', '%d warnings', $warns, 'ctrlfield'), $warns)) . '</span>';
        }
        echo '</span>';
        if ($c->description !== '') {
            echo '<span class="ctrlf-insp-group__where">' . esc_html($c->description) . '</span>';
        }
        echo '<span class="ctrlf-insp-comp__files">' . esc_html($this->shortPath($c->path)) . ' '
            . implode(' ', array_map(static fn (string $f): string => '<span class="ctrlf-badge">' . esc_html($f) . '</span>', $item['files'])) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
            . '</span></span>';
        echo '<span class="ctrlf-insp-group__meta"><span class="ctrlf-insp-group__count">'
            /* translators: %d: number of fields */
            . esc_html(sprintf(_n('%d field', '%d fields', $count, 'ctrlfield'), $count)) . ' · '
            . ($posts === []
                ? '<span class="ctrlf-insp-value is-missing">' . esc_html__('not used', 'ctrlfield') . '</span>'
                /* translators: %d: number of posts */
                : esc_html(sprintf(_n('used on %d post', 'used on %d posts', count($posts), 'ctrlfield'), count($posts))))
            . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
        echo '</summary>';

        echo '<div class="ctrlf-insp-group__body">';
        if ($item['issues'] !== []) {
            echo '<ul class="ctrlf-insp-comp__issues">';
            foreach ($item['issues'] as $issue) {
                echo '<li class="is-' . esc_attr($issue['level']) . '">' . esc_html($issue['message']) . '</li>';
            }
            echo '</ul>';
        }
        if ($posts !== []) {
            $links = array_map(static function (int $id): string {
                $title = get_the_title($id);
                return '<a href="' . esc_url((string) get_edit_post_link($id)) . '">' . esc_html($title !== '' ? $title : '#' . $id) . '</a>';
            }, array_slice($posts, 0, 10));
            echo '<p class="ctrlf-insp-comp__posts">' . esc_html__('Used on:', 'ctrlfield') . ' ' . implode(', ', $links) . (count($posts) > 10 ? ' …' : '') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped parts
        }
        if ($item['fields'] === []) {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('No fields.', 'ctrlfield') . '</p>';
        } else {
            echo '<table class="ctrlf-insp-fields"><thead><tr>'
                . '<th>' . esc_html__('Field', 'ctrlfield') . '</th>'
                . '<th>' . esc_html__('Key', 'ctrlfield') . '</th>'
                . '<th>' . esc_html__('Type', 'ctrlfield') . '</th>'
                . '<th>' . esc_html__('Settings', 'ctrlfield') . '</th>'
                . '</tr></thead><tbody>';
            $this->rows($item['fields'], 0);
            echo '</tbody></table>';
        }
        echo '</div></details>';
    }

    // ── Health ───────────────────────────────────────────────────────────────

    /** The JSON folder URL when its files can be downloaded, null otherwise. */
    private function jsonExposure(): ?string
    {
        if (! function_exists('wp_remote_get') || ! function_exists('get_stylesheet_directory')) {
            return null; // outside WordPress (unit tests)
        }
        $probe = JsonFolderGuard::probe((new FieldGroupRepository())->directory());

        return $probe['status'] === JsonFolderGuard::EXPOSED ? $probe['url'] : null;
    }

    /**
     * @param list<array<string, string>>       $issues
     * @param array<string, string>             $schema  file => error
     * @param list<int>                         $stale
     * @param array<string, array<string, int>> $orphans
     * @param ?string                           $exposed URL of the JSON folder, when its files can be downloaded
     */
    private function health(array $issues, array $schema, array $stale, array $orphans, ?string $exposed = null): void
    {
        $this->actionNotice();

        $items = [];
        if ($exposed !== null) {
            $items[] = [
                'error',
                __('Field group files can be downloaded', 'ctrlfield'),
                /* translators: 1: folder URL, 2: nginx rule */
                sprintf(__('Anyone can download the field group files in %1$s and read every field name, instruction and choice (not your content or the database). CtrlField protects them by itself when it can write to that folder: make it writable and open this page again. Otherwise add this rule to the nginx server block: %2$s — or move the folder outside the web root with the CTRLFIELD_JSON_PATH constant.', 'ctrlfield'), $exposed, JsonFolderGuard::nginxRule($exposed)),
                '',
            ];
        }
        foreach ($schema as $file => $error) {
            $items[] = ['error', __('Not loaded', 'ctrlfield'), $this->shortPath($file) . ' — ' . $error, ''];
        }
        if ($stale !== []) {
            $items[] = [
                'warning',
                __('Older schema version', 'ctrlfield'),
                /* translators: %d: number of posts */
                sprintf(_n('%d post was saved under an older schema version. It is read through the migrations; migrating writes it at the current version.', '%d posts were saved under an older schema version. They are read through the migrations; migrating writes them at the current version.', count($stale), 'ctrlfield'), count($stale)),
                $this->actionButtons('ctrlfield_inspector_migrate', [], __('Migrate', 'ctrlfield')),
            ];
        }
        foreach ($orphans as $type => $keys) {
            arsort($keys);
            $list    = implode(', ', array_map(static fn (string $k, int $n): string => "{$k} ({$n})", array_keys($keys), $keys));
            $items[] = [
                'warning',
                /* translators: %s: post type */
                sprintf(__('Left-over values on %s', 'ctrlfield'), $type),
                /* translators: %s: keys with the number of posts */
                sprintf(__('Values are stored for keys no field group of this post type defines (a key renamed, a field removed): %s. They are never shown; cleaning up removes them.', 'ctrlfield'), $list),
                $this->actionButtons('ctrlfield_inspector_clean', ['post_type' => $type, 'keys' => implode(',', array_keys($keys))], __('Clean up', 'ctrlfield')),
            ];
        }
        $titles = [
            'duplicate_key'    => __('Same key in two groups', 'ctrlfield'),
            'condition_target' => __('Condition on a missing field', 'ctrlfield'),
            'no_label'         => __('Field without a label', 'ctrlfield'),
        ];
        $unlabelled = []; // group => keys: one line per group instead of one per field
        foreach ($issues as $issue) {
            if ($issue['code'] === 'no_label') {
                $unlabelled[$issue['group']][$issue['field']] = true;
                continue;
            }
            $items[] = [$issue['level'], $titles[$issue['code']] ?? $issue['code'], $issue['group'] . ' — ' . $issue['message'], ''];
        }
        foreach ($unlabelled as $groupKey => $keys) {
            $items[] = [
                'info',
                $titles['no_label'],
                /* translators: 1: field group, 2: field keys */
                sprintf(__('%1$s — editors see the key instead of a label: %2$s.', 'ctrlfield'), $groupKey, implode(', ', array_keys($keys))),
                '',
            ];
        }

        if ($items === []) {
            echo '<div class="ctrlf-insp-ok"><strong>' . esc_html__('All good.', 'ctrlfield') . '</strong> '
                . esc_html__('The schema loads, keys and conditions are consistent, and the stored data matches the fields.', 'ctrlfield') . '</div>';
            return;
        }

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($items, static fn (array $a, array $b): int => ($order[$a[0]] ?? 3) <=> ($order[$b[0]] ?? 3));
        $levels   = ['error' => __('Error', 'ctrlfield'), 'warning' => __('Warning', 'ctrlfield'), 'info' => __('Tip', 'ctrlfield')];
        $problems = array_values(array_filter($items, static fn (array $i): bool => $i[0] !== 'info'));
        $tips     = array_values(array_filter($items, static fn (array $i): bool => $i[0] === 'info'));
        $list     = static function (array $items) use ($levels): void {
            echo '<ul class="ctrlf-insp-issues">';
            foreach ($items as [$level, $title, $text, $actions]) {
                printf(
                    '<li class="ctrlf-insp-issue ctrlf-insp-issue--%1$s"><span class="ctrlf-badge ctrlf-badge--%1$s">%2$s</span><div><strong>%3$s</strong><p>%4$s</p>%5$s</div></li>',
                    esc_attr($level),
                    esc_html($levels[$level] ?? $level),
                    esc_html($title),
                    esc_html($text),
                    $actions // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
                );
            }
            echo '</ul>';
        };

        if ($problems === []) {
            echo '<div class="ctrlf-insp-ok"><strong>' . esc_html__('No problems.', 'ctrlfield') . '</strong> '
                . esc_html__('The schema loads, keys and conditions are consistent, and the stored data matches the fields.', 'ctrlfield') . '</div>';
        } else {
            $list($problems);
        }
        if ($tips !== []) {
            /* translators: %d: number of tips */
            echo '<details class="ctrlf-insp-tips"><summary>' . esc_html(sprintf(_n('%d tip', '%d tips', count($tips), 'ctrlfield'), count($tips))) . '</summary>';
            $list($tips);
            echo '</details>';
        }
    }

    /**
     * "Preview" (dry run) and the fix itself.
     *
     * @param array<string, string> $args
     */
    private function actionButtons(string $action, array $args, string $label): string
    {
        return '<p class="ctrlf-insp-issue__actions">'
            . '<a class="button button-small" href="' . esc_url(InspectorActions::url($action, $args)) . '">' . esc_html__('Preview (dry run)', 'ctrlfield') . '</a> '
            . '<a class="button button-small button-primary" href="' . esc_url(InspectorActions::url($action, $args + ['apply' => '1'])) . '"'
            . ' onclick="return confirm(' . esc_attr(\CtrlField\Admin\Js::json(__('This changes stored data. Continue?', 'ctrlfield'))) . ')">' . esc_html($label) . '</a></p>';
    }

    private function actionNotice(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display of an action's result
        $done = isset($_GET['ctrlf_done']) ? sanitize_key(wp_unslash((string) $_GET['ctrlf_done'])) : '';
        if ($done === '') {
            return;
        }
        $dry = ! empty($_GET['ctrlf_dry']);
        $n   = isset($_GET['ctrlf_n']) ? (int) $_GET['ctrlf_n'] : 0;
        // phpcs:enable
        /* translators: %d: number of posts */
        $message = match ($done) {
            'migrate' => $dry
                ? sprintf(_n('Dry run: %d post would be migrated.', 'Dry run: %d posts would be migrated.', $n, 'ctrlfield'), $n)
                : sprintf(_n('%d post migrated.', '%d posts migrated.', $n, 'ctrlfield'), $n),
            'clean'   => $dry
                ? sprintf(_n('Dry run: %d post would be cleaned up.', 'Dry run: %d posts would be cleaned up.', $n, 'ctrlfield'), $n)
                : sprintf(_n('%d post cleaned up.', '%d posts cleaned up.', $n, 'ctrlfield'), $n),
            default   => '',
        };
        if ($message !== '') {
            // The plugin's own message (not a WP .notice, which core moves above the page title).
            echo MessageRenderer::dismissible($dry ? 'info' : 'success', esc_html($message)); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped
        }
    }

    private function shortPath(string $file): string
    {
        return defined('WP_CONTENT_DIR') ? str_replace(WP_CONTENT_DIR, 'wp-content', $file) : $file;
    }

    // ── Import / Export ──────────────────────────────────────────────────────

    private function importExport(): void
    {
        echo '<p class="ctrlf-insp__lead">' . esc_html__('Export the posts of a group as CSV (one column per field), edit them in a spreadsheet and import them back. Rows are matched by post ID; existing values are updated.', 'ctrlfield') . '</p>';

        $rows = '';
        foreach ($this->groups as $key => $group) {
            $type = SchemaAnalyzer::postTypes($group)[0] ?? null;
            if ($type === null) {
                continue;
            }
            $rows .= sprintf(
                '<tr><td><strong>%1$s</strong> <code>%2$s</code></td><td>%3$s</td><td class="ctrlf-insp-io__actions" x-data="ctrlfCsvImport(%6$s)">'
                . '<a class="button" href="%4$s">%5$s</a> '
                . '<label class="button" :class="{ \'disabled\': busy }"><span x-text="busy ? %7$s : %8$s"></span>'
                . '<input type="file" accept=".csv" hidden @change="send($event.target)"></label>'
                . '<span class="ctrlf-insp-io__result" :class="{ \'is-error\': failed }" x-show="result" x-text="result"></span>'
                . '</td></tr>',
                esc_html($group->getTitle() !== '' ? $group->getTitle() : (string) $key),
                esc_html((string) $key),
                esc_html($type),
                esc_url(InspectorActions::url('ctrlfield_export_csv', ['group' => (string) $key])),
                esc_html__('Export CSV', 'ctrlfield'),
                esc_attr(\CtrlField\Admin\Js::json(['group' => (string) $key, 'nonce' => wp_create_nonce('ctrlfield_csv_import_' . $key)])),
                esc_attr(\CtrlField\Admin\Js::json(__('Importing…', 'ctrlfield'))),
                esc_attr(\CtrlField\Admin\Js::json(__('Import CSV', 'ctrlfield')))
            );
        }

        if ($rows === '') {
            echo '<p class="ctrlf-insp__empty">' . esc_html__('No group is shown on a post type, so there is nothing to export.', 'ctrlfield') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat striped ctrlf-insp-io"><thead><tr><th>' . esc_html__('Group', 'ctrlfield') . '</th><th>'
            . esc_html__('Post type', 'ctrlfield') . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
