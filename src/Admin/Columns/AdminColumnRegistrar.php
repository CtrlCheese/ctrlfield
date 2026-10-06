<?php

declare(strict_types=1);

namespace CtrlField\Admin\Columns;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Types\CheckboxField;
use CtrlField\Fields\Types\DateField;
use CtrlField\Fields\Types\DateTimeField;
use CtrlField\Fields\Types\ImageField;
use CtrlField\Fields\Types\LinkField;
use CtrlField\Fields\Types\SelectField;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Storage\PostMetaAdapter;

/**
 * Registers custom field values as columns in the WP post list table.
 * Excluded from PHPStan — references WP_Query and WP admin functions.
 */
final class AdminColumnRegistrar
{
    /**
     * postType → [fieldKey => FieldDefinition]
     *
     * @var array<string, array<string, FieldDefinition>>
     */
    private array $columnFields = [];

    public function register(): void
    {
        $this->buildColumnMap();

        foreach (array_keys($this->columnFields) as $postType) {
            add_filter("manage_{$postType}_posts_columns",         [$this, 'addColumns']);
            add_action("manage_{$postType}_posts_custom_column",   [$this, 'renderCell'], 10, 2);
            add_filter("manage_edit-{$postType}_sortable_columns", [$this, 'makeSortable']);
        }

        if (! empty($this->columnFields)) {
            add_action('pre_get_posts', [$this, 'handleSort']);
        }
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    /** @param array<string, string> $columns */
    public function addColumns(array $columns): array
    {
        $postType = get_current_screen()?->post_type ?? '';
        $fields   = $this->columnFields[$postType] ?? [];

        if (empty($fields)) {
            return $columns;
        }

        // Insert after the 'title' column
        $pos = array_search('title', array_keys($columns), true);

        if ($pos === false) {
            foreach ($fields as $key => $field) {
                $columns['ctrlfield_' . $key] = esc_html($field->getAdminColumnLabel());
            }
            return $columns;
        }

        $before = array_slice($columns, 0, $pos + 1, true);
        $after  = array_slice($columns, $pos + 1, null, true);

        $new = [];
        foreach ($fields as $key => $field) {
            $new['ctrlfield_' . $key] = esc_html($field->getAdminColumnLabel());
        }

        return array_merge($before, $new, $after);
    }

    public function renderCell(string $column, int $postId): void
    {
        if (! str_starts_with($column, 'ctrlfield_')) {
            return;
        }

        $fieldKey = substr($column, strlen('ctrlfield_'));
        $field    = $this->findFieldByKey($fieldKey);

        if ($field === null) {
            return;
        }

        $value = PostMetaAdapter::loadSingleField($postId, $fieldKey);

        echo $this->formatColumnValue($field, $value); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /** @param array<string, string> $columns */
    public function makeSortable(array $columns): array
    {
        $postType = get_current_screen()?->post_type ?? '';
        $fields   = $this->columnFields[$postType] ?? [];

        foreach ($fields as $key => $field) {
            if ($field->isAdminColumnSortable() && $field->isIndex()) {
                $columns['ctrlfield_' . $key] = 'ctrlfield_' . $key;
            }
        }

        return $columns;
    }

    public function handleSort(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        $orderby = (string) $query->get('orderby');

        if (! str_starts_with($orderby, 'ctrlfield_')) {
            return;
        }

        $fieldKey = substr($orderby, strlen('ctrlfield_'));
        $field    = $this->findFieldByKey($fieldKey);

        if ($field === null) {
            return;
        }

        $query->set('meta_key', PostMetaAdapter::INDEX_KEY_PREFIX . $fieldKey);
        $query->set('orderby', $field->isNumericSort() ? 'meta_value_num' : 'meta_value');
    }

    // -------------------------------------------------------------------------
    // Column value formatting
    // -------------------------------------------------------------------------

    private function formatColumnValue(FieldDefinition $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '<span aria-hidden="true">—</span>';
        }

        return match ($field->getType()) {
            FieldType::NUMBER,
            FieldType::RANGE     => esc_html((string) $value),

            FieldType::DATE      => $this->formatDate($field, $value),
            FieldType::DATETIME  => $this->formatDateTime($field, $value),
            FieldType::TIME      => esc_html((string) $value),
            FieldType::COLOR     => $this->formatColor((string) $value),

            FieldType::IMAGE     => $this->formatImage($value),
            FieldType::FILE      => esc_html((string) $value),

            FieldType::SELECT,
            FieldType::RADIO     => $this->formatOption($field, (string) $value),
            FieldType::CHECKBOX  => $this->formatCheckbox($field, $value),

            FieldType::LINK      => $this->formatLink($value),
            FieldType::WYSIWYG   => esc_html(mb_substr(strip_tags((string) $value), 0, 80)),

            FieldType::REPEATER,
            FieldType::GROUP     => $this->formatCount($value),

            default              => esc_html(mb_substr((string) $value, 0, 50)),
        };
    }

    private function formatDate(FieldDefinition $field, mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }
        $format = $field instanceof DateField ? $field->getFormat() : 'Y-m-d';
        $ts     = strtotime($value);
        return $ts !== false
            ? esc_html(function_exists('date_i18n') ? date_i18n($format, $ts) : date($format, $ts))
            : esc_html($value);
    }

    private function formatDateTime(FieldDefinition $field, mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }
        $format = $field instanceof DateTimeField ? $field->getFormat() : 'Y-m-d H:i';
        $ts     = strtotime($value);
        return $ts !== false
            ? esc_html(function_exists('date_i18n') ? date_i18n($format, $ts) : date($format, $ts))
            : esc_html($value);
    }

    private function formatColor(string $value): string
    {
        return sprintf(
            '<span style="display:inline-block;width:16px;height:16px;background:%s;border:1px solid #ccc;vertical-align:middle;margin-right:4px;border-radius:2px"></span>%s',
            esc_attr($value),
            esc_html($value),
        );
    }

    private function formatImage(mixed $value): string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return '—';
        }
        $url = function_exists('wp_get_attachment_image_url')
            ? wp_get_attachment_image_url((int) $value, [40, 40])
            : false;

        return $url
            ? sprintf('<img src="%s" width="40" height="40" style="object-fit:cover">', esc_url($url))
            : esc_html((string) $value);
    }

    private function formatOption(FieldDefinition $field, string $value): string
    {
        if ($field instanceof SelectField) {
            return esc_html($field->getOptions()[$value] ?? $value);
        }
        return esc_html($value);
    }

    private function formatCheckbox(FieldDefinition $field, mixed $value): string
    {
        if (! is_array($value)) {
            return '—';
        }
        if ($field instanceof CheckboxField) {
            $options = $field->getOptions();
            $labels  = array_map(static fn($v) => $options[$v] ?? $v, $value);
            return esc_html(implode(', ', $labels));
        }
        return esc_html(implode(', ', $value));
    }

    private function formatLink(mixed $value): string
    {
        if (! is_array($value) || empty($value['url'])) {
            return '—';
        }
        $title  = $value['title'] ?: $value['url'];
        $target = ($value['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
        return sprintf('<a href="%s"%s>%s</a>', esc_url($value['url']), $target, esc_html($title));
    }

    private function formatCount(mixed $value): string
    {
        $count = is_array($value) ? count($value) : 0;
        return esc_html($count . ' ' . ($count === 1 ? 'item' : 'items'));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function buildColumnMap(): void
    {
        foreach (FieldRegistry::all() as $group) {
            // Only post groups — resolve post type from AND conditions
            $postType = null;
            foreach ($group->getAndConditions() as $condition) {
                if ($condition['key'] === 'post_type' && $condition['operator'] === '==') {
                    $postType = (string) $condition['value'];
                    break;
                }
            }

            if ($postType === null) {
                continue; // user/term/comment groups — not supported for admin columns in v2
            }

            foreach ($group->getFields() as $field) {
                if ($field->isAdminColumn()) {
                    $this->columnFields[$postType][$field->getKey()] = $field;
                }
            }
        }
    }

    private function findFieldByKey(string $fieldKey): ?FieldDefinition
    {
        foreach ($this->columnFields as $fields) {
            if (isset($fields[$fieldKey])) {
                return $fields[$fieldKey];
            }
        }
        return null;
    }
}
