<?php

declare(strict_types=1);

namespace CtrlField\Compat\Acf;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FlexibleContentInterface;
use CtrlField\Fields\Contracts\NestedFieldInterface;
use CtrlField\Fields\FieldDefinition;

/**
 * Value conversions between ACF and CtrlField.
 *
 *  - readMeta():  ACF's stored meta (name, name_0_sub, …) → ACF "unformatted" values
 *  - toStorage(): ACF-style values (update_field(), import) → CtrlField storage format
 *  - format():    CtrlField stored values → what ACF's get_field() returns
 */
final class AcfValues
{
    // -------------------------------------------------------------------------
    // ACF meta → values
    // -------------------------------------------------------------------------

    /**
     * Read one ACF field from a meta source.
     *
     * @param array<string, mixed>     $acfField ACF field array
     * @param callable(string): mixed  $get      meta name → value, null when the meta does not exist
     * @return array{0: bool, 1: mixed} [found, value] — sub-field keys are CtrlField keys
     */
    public static function readMeta(array $acfField, callable $get, string $name): array
    {
        $type = (string) ($acfField['type'] ?? '');
        $raw  = $get($name);

        if ($type === 'repeater') {
            if ($raw === null) {
                return [false, null];
            }
            $rows = [];
            for ($i = 0, $n = (int) $raw; $i < $n; $i++) {
                $rows[] = self::readSubs($acfField['sub_fields'] ?? [], $get, "{$name}_{$i}_");
            }
            return [true, $rows];
        }

        if ($type === 'flexible_content') {
            if (! is_array($raw)) {
                return [$raw !== null, []];
            }
            $layouts = [];
            foreach (is_array($acfField['layouts'] ?? null) ? $acfField['layouts'] : [] as $layout) {
                if (is_array($layout)) {
                    $layouts[(string) ($layout['name'] ?? '')] = $layout;
                }
            }
            $rows = [];
            foreach (array_values($raw) as $i => $layoutName) {
                $layout = $layouts[(string) $layoutName] ?? null;
                if ($layout === null) {
                    continue;
                }
                $rows[] = ['acf_fc_layout' => AcfConverter::keyFor((string) $layoutName)]
                    + self::readSubs($layout['sub_fields'] ?? [], $get, "{$name}_{$i}_");
            }
            return [true, $rows];
        }

        if ($type === 'group') {
            $values = self::readSubs($acfField['sub_fields'] ?? [], $get, "{$name}_");
            return [$values !== [], $values];
        }

        return [$raw !== null, $raw];
    }

    /**
     * @param  mixed                   $subFields
     * @param  callable(string): mixed $get
     * @return array<string, mixed>
     */
    private static function readSubs(mixed $subFields, callable $get, string $prefix): array
    {
        $values = [];
        foreach (is_array($subFields) ? $subFields : [] as $sub) {
            if (! is_array($sub) || ($sub['name'] ?? '') === '') {
                continue;
            }
            [$found, $value] = self::readMeta($sub, $get, $prefix . $sub['name']);
            if ($found) {
                $values[AcfConverter::keyFor((string) $sub['name'])] = $value;
            }
        }

        return $values;
    }

    // -------------------------------------------------------------------------
    // ACF-style values → CtrlField storage
    // -------------------------------------------------------------------------

    public static function toStorage(mixed $value, FieldDefinition $def): mixed
    {
        $type = $def->getType();

        return match ($type) {
            FieldType::REPEATER         => self::rowsToStorage($value, $def),
            FieldType::GROUP            => is_array($value) ? self::subsToStorage($value, self::subDefs($def)) : [],
            FieldType::FLEXIBLE_CONTENT => self::flexToStorage($value, $def),
            FieldType::DATE             => self::date($value, 'Y-m-d', ['Ymd', 'Y-m-d', 'd/m/Y']),
            FieldType::DATETIME         => self::date($value, 'Y-m-d\TH:i:s', ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i']),
            FieldType::TIME             => self::date($value, 'H:i', ['H:i:s', 'H:i']),
            FieldType::TRUE_FALSE       => is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (bool) $value,
            FieldType::IMAGE, FieldType::FILE => self::id($value) ?? 0,
            FieldType::GALLERY, FieldType::RELATIONSHIP => self::ids($value),
            FieldType::POST_OBJECT, FieldType::PAGE_LINK, FieldType::USER, FieldType::TAXONOMY_TERM
                => self::isMultiple($def) ? self::ids($value) : (self::ids($value)[0] ?? null),
            FieldType::CHECKBOX         => array_values(array_map('strval', is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]))),
            FieldType::NUMBER, FieldType::RANGE => is_numeric($value) ? 0 + $value : null,
            FieldType::LINK             => is_string($value) ? ['url' => $value, 'title' => '', 'target' => ''] : $value,
            default                     => $value,
        };
    }

    /** @return list<array<string, mixed>> */
    private static function rowsToStorage(mixed $rows, FieldDefinition $def): array
    {
        $subs = self::subDefs($def);
        $out  = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $out[] = self::subsToStorage($row, $subs);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function flexToStorage(mixed $rows, FieldDefinition $def): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $layout = (string) ($row['acf_fc_layout'] ?? $row['_layout'] ?? '');
            $subs   = [];
            if ($def instanceof FlexibleContentInterface && in_array($layout, $def->getLayoutKeys(), true)) {
                foreach ($def->getLayoutFields($layout) as $sub) {
                    $subs[$sub->getKey()] = $sub;
                }
            }
            unset($row['acf_fc_layout'], $row['_layout']);
            $out[] = ['_layout' => $layout] + self::subsToStorage($row, $subs);
        }

        return $out;
    }

    /**
     * @param  array<mixed>                   $values
     * @param  array<string, FieldDefinition> $subs
     * @return array<string, mixed>
     */
    private static function subsToStorage(array $values, array $subs): array
    {
        $out = [];
        foreach ($subs as $key => $sub) {
            if (array_key_exists($key, $values)) {
                $out[$key] = self::toStorage($values[$key], $sub);
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // CtrlField storage → get_field() output
    // -------------------------------------------------------------------------

    public static function format(mixed $value, FieldDefinition $def): mixed
    {
        $type = $def->getType();
        $rf   = $def->getReturnFormat();

        if (in_array($type, [FieldType::TAB, FieldType::MESSAGE, FieldType::SEPARATOR, FieldType::ACCORDION, FieldType::ACCORDION_END], true)) {
            return null;
        }
        if ($value === null) {
            return null;
        }

        $emptyIsFalse = [FieldType::IMAGE, FieldType::FILE, FieldType::GALLERY, FieldType::RELATIONSHIP,
            FieldType::POST_OBJECT, FieldType::PAGE_LINK, FieldType::USER, FieldType::TAXONOMY_TERM,
            FieldType::REPEATER, FieldType::FLEXIBLE_CONTENT];
        if (in_array($type, $emptyIsFalse, true) && ($value === '' || $value === [] || $value === 0 || $value === '0')) {
            return false;
        }

        switch ($type) {
            case FieldType::TRUE_FALSE:
                return (bool) $value;

            case FieldType::IMAGE:
            case FieldType::FILE:
                return self::attachmentAs((int) $value, $rf ?: 'array');

            case FieldType::GALLERY:
                return array_values(array_filter(array_map(
                    static fn ($id) => self::attachmentAs((int) $id, $rf ?: 'array'),
                    (array) $value
                )));

            case FieldType::SELECT:
            case FieldType::RADIO:
            case FieldType::BUTTON_GROUP:
                return self::choice($value, $def, $rf);

            case FieldType::CHECKBOX:
                return array_map(static fn ($v) => self::choice($v, $def, $rf), (array) $value);

            case FieldType::POST_OBJECT:
            case FieldType::RELATIONSHIP:
                $map = $rf === 'id'
                    ? static fn ($id) => (int) $id
                    : static fn ($id) => get_post((int) $id);
                return is_array($value) ? array_values(array_filter(array_map($map, $value))) : $map($value);

            case FieldType::PAGE_LINK:
                $link = static fn ($id) => (string) get_permalink((int) $id);
                return is_array($value) ? array_map($link, $value) : $link($value);

            case FieldType::TAXONOMY_TERM:
                $map = $rf === 'object'
                    ? static fn ($id) => get_term((int) $id)
                    : static fn ($id) => (int) $id;
                return is_array($value) ? array_values(array_map($map, $value)) : $map($value);

            case FieldType::USER:
                $map = static fn ($id) => self::userAs((int) $id, $rf ?: 'array');
                return is_array($value) ? array_values(array_filter(array_map($map, $value))) : $map($value);

            case FieldType::LINK:
                if ($rf === 'url') {
                    return is_array($value) ? (string) ($value['url'] ?? '') : (string) $value;
                }
                return is_array($value) ? ['title' => (string) ($value['title'] ?? ''), 'url' => (string) ($value['url'] ?? ''), 'target' => (string) ($value['target'] ?? '') === '_self' ? '' : (string) ($value['target'] ?? '')] : $value;

            case FieldType::DATE:
            case FieldType::DATETIME:
            case FieldType::TIME:
                return self::formatDate((string) $value, $type, $rf);

            case FieldType::WYSIWYG:
                return self::content((string) $value);

            case FieldType::OEMBED:
                return self::embed((string) $value);

            case FieldType::GROUP:
                return self::formatSubs(is_array($value) ? $value : [], self::subDefs($def));

            case FieldType::REPEATER:
                $subs = self::subDefs($def);
                return array_map(static fn ($row) => self::formatSubs(is_array($row) ? $row : [], $subs), array_values((array) $value));

            case FieldType::FLEXIBLE_CONTENT:
                $out = [];
                foreach ((array) $value as $row) {
                    if (! is_array($row) || ! $def instanceof FlexibleContentInterface) {
                        continue;
                    }
                    $layout = (string) ($row['_layout'] ?? '');
                    $subs   = [];
                    if (in_array($layout, $def->getLayoutKeys(), true)) {
                        foreach ($def->getLayoutFields($layout) as $sub) {
                            $subs[$sub->getKey()] = $sub;
                        }
                    }
                    $out[] = ['acf_fc_layout' => $layout] + self::formatSubs($row, $subs);
                }
                return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>           $row
     * @param  array<string, FieldDefinition> $subs
     * @return array<string, mixed>
     */
    public static function formatSubs(array $row, array $subs): array
    {
        $out = [];
        foreach ($subs as $key => $sub) {
            $out[$key] = self::format($row[$key] ?? null, $sub);
        }

        return $out;
    }

    /** @return array<string, FieldDefinition> */
    public static function subDefs(FieldDefinition $def): array
    {
        $out = [];
        if ($def instanceof NestedFieldInterface) {
            foreach ($def->getFields() as $sub) {
                $out[$sub->getKey()] = $sub;
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    private static function choice(mixed $value, FieldDefinition $def, string $rf): mixed
    {
        if ($rf !== 'label' && $rf !== 'array') {
            return $value;
        }
        $options = method_exists($def, 'getOptions') ? (array) $def->getOptions() : [];
        $label   = $options[(string) $value] ?? $value;

        return $rf === 'label' ? $label : ['value' => $value, 'label' => $label];
    }

    private static function attachmentAs(int $id, string $rf): mixed
    {
        return match ($rf) {
            'id'    => $id,
            'url'   => wp_get_attachment_url($id) ?: false,
            default => self::attachment($id) ?? false,
        };
    }

    /**
     * The array ACF returns for images and files (acf_get_attachment()).
     *
     * @return array<string, mixed>|null
     */
    public static function attachment(int $id): ?array
    {
        $post = get_post($id);
        if (! $post instanceof \WP_Post || $post->post_type !== 'attachment') {
            return null;
        }

        $url  = (string) wp_get_attachment_url($id);
        $meta = wp_get_attachment_metadata($id) ?: [];
        [$type, $subtype] = array_pad(explode('/', (string) $post->post_mime_type, 2), 2, '');

        $a = [
            'ID' => $id, 'id' => $id, 'title' => $post->post_title, 'filename' => wp_basename($url),
            'filesize' => (int) ($meta['filesize'] ?? 0), 'url' => $url, 'link' => get_attachment_link($id),
            'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'author' => $post->post_author, 'description' => $post->post_content, 'caption' => $post->post_excerpt,
            'name' => $post->post_name, 'status' => $post->post_status, 'uploaded_to' => $post->post_parent,
            'date' => $post->post_date_gmt, 'modified' => $post->post_modified_gmt, 'menu_order' => $post->menu_order,
            'mime_type' => $post->post_mime_type, 'type' => $type, 'subtype' => $subtype,
            'icon' => wp_mime_type_icon($id),
        ];

        if ($type === 'image') {
            $a['width']  = (int) ($meta['width'] ?? 0);
            $a['height'] = (int) ($meta['height'] ?? 0);
            $a['sizes']  = [];
            foreach (get_intermediate_image_sizes() as $size) {
                $src = wp_get_attachment_image_src($id, $size);
                if (is_array($src)) {
                    $a['sizes'][$size]             = $src[0];
                    $a['sizes'][$size . '-width']  = $src[1];
                    $a['sizes'][$size . '-height'] = $src[2];
                }
            }
        }

        return $a;
    }

    private static function userAs(int $id, string $rf): mixed
    {
        if ($rf === 'id') {
            return $id;
        }
        $user = get_userdata($id);
        if (! $user instanceof \WP_User) {
            return null;
        }
        if ($rf === 'object') {
            return $user;
        }

        return [
            'ID' => $user->ID, 'user_firstname' => $user->user_firstname, 'user_lastname' => $user->user_lastname,
            'nickname' => $user->nickname, 'user_nicename' => $user->user_nicename, 'display_name' => $user->display_name,
            'user_email' => $user->user_email, 'user_url' => $user->user_url, 'user_registered' => $user->user_registered,
            'user_description' => $user->user_description, 'user_avatar' => get_avatar($user->ID),
        ];
    }

    private static function formatDate(string $value, FieldType $type, string $rf): mixed
    {
        if ($value === '') {
            return '';
        }
        $ts = strtotime(str_replace('T', ' ', $value));
        if ($ts === false) {
            return $value;
        }
        if ($rf === 'timestamp') {
            return $ts;
        }
        if ($rf === 'DateTime') {
            return new \DateTime('@' . $ts);
        }
        $default = match ($type) {
            FieldType::DATETIME => 'd/m/Y g:i a',
            FieldType::TIME     => 'g:i a',
            default             => 'd/m/Y',
        };

        // Stored values are site-local times: format them without a timezone shift.
        return date_i18n($rf !== '' ? $rf : $default, $ts, true);
    }

    /** ACF's acf_the_content filter chain (the_content without other plugins' additions). */
    private static function content(string $html): string
    {
        foreach (['wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'do_shortcode'] as $fn) {
            if (function_exists($fn)) {
                $html = (string) $fn($html);
            }
        }

        return function_exists('wp_filter_content_tags') ? wp_filter_content_tags($html) : $html;
    }

    private static function embed(string $url): string
    {
        $embed = $GLOBALS['wp_embed'] ?? null;
        if ($url === '' || ! $embed instanceof \WP_Embed) {
            return $url;
        }
        $html = $embed->shortcode([], $url); // cached in post meta by WordPress

        return is_string($html) && $html !== '' ? $html : $url;
    }

    private static function isMultiple(FieldDefinition $def): bool
    {
        return method_exists($def, 'isMultiple') && $def->isMultiple();
    }

    private static function id(mixed $v): ?int
    {
        if (is_object($v)) {
            $v = $v->ID ?? $v->term_id ?? null;
        } elseif (is_array($v)) {
            $v = $v['ID'] ?? $v['id'] ?? $v['term_id'] ?? null;
        }

        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    /** @return list<int> */
    private static function ids(mixed $v): array
    {
        if ($v === null || $v === '' || $v === false) {
            return [];
        }
        // One ACF array (attachment / user / term) or a list of IDs / objects.
        $single = is_array($v) && (isset($v['ID']) || isset($v['id']) || isset($v['term_id']));
        $list   = is_array($v) && ! $single ? $v : [$v];

        return array_values(array_filter(array_map(self::id(...), $list)));
    }

    /** @param list<string> $from */
    private static function date(mixed $value, string $to, array $from): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($to);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        foreach ($from as $format) {
            $dt = \DateTime::createFromFormat('!' . $format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return $dt->format($to);
            }
        }
        $ts = strtotime($value);

        return $ts === false ? $value : gmdate($to, $ts);
    }
}
