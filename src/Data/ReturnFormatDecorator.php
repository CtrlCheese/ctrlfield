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

        // GALLERY stores an array of IDs — return an array of URLs
        if ($type === FieldType::GALLERY) {
            if (! is_array($value)) {
                return [];
            }
            return array_map(
                static fn(mixed $id): string => (string) (
                    function_exists('wp_get_attachment_url') ? wp_get_attachment_url((int) $id) : ''
                ),
                $value,
            );
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
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

        return (array) $value;
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
