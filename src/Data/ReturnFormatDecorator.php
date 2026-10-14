<?php

declare(strict_types=1);

namespace CtrlField\Data;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\FieldDefinition;

/**
 * Transforms raw stored values into richer types based on ->returnFormat().
 *
 * Applied only in the read path (FieldDataService::get).
 * Storage always contains primitives — IDs, strings, arrays.
 *
 * Excluded from PHPStan — references WP attachment functions.
 */
final class ReturnFormatDecorator
{
    public static function apply(mixed $value, FieldDefinition $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->getReturnFormat()) {
            'url'       => self::toUrl($value, $field->getType()),
            'array'     => self::toArray($value, $field->getType()),
            'object'    => self::toObject($value, $field->getType()),
            'DateTime'  => self::toDateTime($value),
            'timestamp' => self::toTimestamp($value),
            default     => $value,
        };
    }

    private static function toUrl(mixed $value, FieldType $type): mixed
    {
        if ($type === FieldType::IMAGE || $type === FieldType::FILE) {
            $id = (int) $value;
            return (string) (function_exists('wp_get_attachment_url') ? wp_get_attachment_url($id) : '');
        }

        // PAGE_LINK stores post IDs — return permalinks (one or a list)
        if ($type === FieldType::PAGE_LINK) {
            $link = static fn (mixed $id): string => (string) (
                function_exists('get_permalink') ? get_permalink((int) $id) : ''
            );
            return is_array($value) ? array_map($link, $value) : $link($value);
        }

        // GALLERY stores an array of IDs — return an array of URLs
        if ($type === FieldType::GALLERY) {
            if (! is_array($value)) {
                return [];
            }
            return array_values(array_filter(array_map(
                static fn(mixed $id): string => (string) (
                    function_exists('wp_get_attachment_url') ? wp_get_attachment_url((int) $id) : ''
                ),
                $value,
            ))); // deleted attachments drop out instead of returning ''
        }

        return (string) $value;
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private static function toArray(mixed $value, FieldType $type): array
    {
        if ($type === FieldType::IMAGE) {
            $id  = (int) $value;
            $url = function_exists('wp_get_attachment_image_url')
                ? (wp_get_attachment_image_url($id, 'full') ?: '')
                : '';
            $meta = function_exists('wp_get_attachment_metadata')
                ? (wp_get_attachment_metadata($id) ?: [])
                : [];
            $alt = function_exists('get_post_meta')
                ? (string) get_post_meta($id, '_wp_attachment_image_alt', true)
                : '';

            return [
                'id'     => $id,
                'url'    => $url,
                'width'  => (int) ($meta['width']  ?? 0),
                'height' => (int) ($meta['height'] ?? 0),
                'alt'    => $alt,
            ];
        }

        if ($type === FieldType::FILE) {
            $id  = (int) $value;
            $url = function_exists('wp_get_attachment_url')
                ? (string) (wp_get_attachment_url($id) ?: '')
                : '';
            $name = function_exists('get_the_title') ? (string) get_the_title($id) : '';
            $mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($id) : '';

            return [
                'id'   => $id,
                'url'  => $url,
                'name' => $name,
                'mime' => $mime,
            ];
        }

        // USER / TAXONOMY_TERM store an ID (or a list with ->multiple()):
        // the ACF-like array of each one; ones that no longer exist drop out.
        if ($type === FieldType::USER || $type === FieldType::TAXONOMY_TERM) {
            $one = $type === FieldType::USER ? self::userArray(...) : self::termArray(...);
            if (is_array($value)) {
                return array_values(array_filter(array_map($one, $value)));
            }
            return $one($value) ?? [];
        }

        return (array) $value;
    }

    /**
     * A user as ACF's user field returns it with return_format 'array' — the
     * same shape for ctrlfield_get() and get_field(). Null when the user is gone.
     *
     * @return array<string, mixed>|null
     */
    public static function userArray(mixed $id): ?array
    {
        $user = function_exists('get_userdata') ? get_userdata((int) $id) : false;
        if (! is_object($user)) {
            return null;
        }

        return [
            'ID'               => (int) $user->ID,
            'user_firstname'   => (string) ($user->user_firstname ?? ''),
            'user_lastname'    => (string) ($user->user_lastname ?? ''),
            'nickname'         => (string) ($user->nickname ?? ''),
            'user_nicename'    => (string) ($user->user_nicename ?? ''),
            'display_name'     => (string) ($user->display_name ?? ''),
            'user_email'       => (string) ($user->user_email ?? ''),
            'user_url'         => (string) ($user->user_url ?? ''),
            'user_registered'  => (string) ($user->user_registered ?? ''),
            'user_description' => (string) ($user->user_description ?? ''),
            'user_avatar'      => function_exists('get_avatar') ? (string) get_avatar((int) $user->ID) : '',
        ];
    }

    /** @return array<string, mixed>|null */
    private static function termArray(mixed $id): ?array
    {
        $term = function_exists('get_term') ? get_term((int) $id) : null;
        if (! $term instanceof \WP_Term) {
            return null;
        }
        $link = function_exists('get_term_link') ? get_term_link($term) : '';

        return [
            'term_id'     => (int) $term->term_id,
            'name'        => (string) $term->name,
            'slug'        => (string) $term->slug,
            'taxonomy'    => (string) $term->taxonomy,
            'description' => $term->description,
            'count'       => $term->count,
            'url'         => is_string($link) ? $link : '',
        ];
    }

    private static function toObject(mixed $value, FieldType $type): mixed
    {
        if ($type === FieldType::POST_OBJECT) {
            if (! function_exists('get_post')) {
                return null;
            }
            // POST_OBJECT may store a single ID or an array of IDs
            if (is_array($value)) {
                return array_map(static fn(mixed $id) => get_post((int) $id), $value);
            }
            return get_post((int) $value);
        }

        if ($type === FieldType::RELATIONSHIP) {
            if (! function_exists('get_post')) {
                return [];
            }
            // RELATIONSHIP always stores an array of IDs
            $ids = is_array($value) ? $value : [$value];
            return array_values(
                array_filter(
                    array_map(static fn(mixed $id) => get_post((int) $id), $ids),
                )
            );
        }

        if ($type === FieldType::USER || $type === FieldType::TAXONOMY_TERM) {
            $one = $type === FieldType::USER
                ? static fn (mixed $id): mixed => function_exists('get_userdata') ? (get_userdata((int) $id) ?: null) : null
                : static function (mixed $id): mixed {
                    $term = function_exists('get_term') ? get_term((int) $id) : null;
                    return is_object($term) && ! (function_exists('is_wp_error') && is_wp_error($term)) ? $term : null;
                };
            return is_array($value) ? array_values(array_filter(array_map($one, $value))) : $one($value);
        }

        return $value;
    }

    private static function toDateTime(mixed $value): ?\DateTime
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'H:i:s', 'H:i'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        return null;
    }

    private static function toTimestamp(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $dt = self::toDateTime($value);
        return $dt !== null ? $dt->getTimestamp() : 0;
    }
}
