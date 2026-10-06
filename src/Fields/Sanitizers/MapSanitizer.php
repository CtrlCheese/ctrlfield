<?php

declare(strict_types=1);

namespace CtrlField\Fields\Sanitizers;

/**
 * Sanitizes map field values.
 *
 * - lat: float clamped to [-90, 90]
 * - lng: float clamped to [-180, 180]
 * - zoom: int clamped to [1, 20]
 * - address: sanitize_text_field()
 */
final class MapSanitizer
{
    /**
     * @param  mixed $value  array or JSON string
     * @return array{lat: float, lng: float, zoom: int, address: string}
     */
    public function sanitize(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        $lat  = is_numeric($value['lat'] ?? null)  ? (float) $value['lat']  : 0.0;
        $lng  = is_numeric($value['lng'] ?? null)  ? (float) $value['lng']  : 0.0;
        $zoom = isset($value['zoom']) && is_numeric($value['zoom']) ? (int) $value['zoom'] : 14;
        $addr = isset($value['address']) && is_string($value['address']) ? $value['address'] : '';

        return [
            'lat'     => max(-90.0,  min(90.0,  $lat)),
            'lng'     => max(-180.0, min(180.0, $lng)),
            'zoom'    => max(1,      min(20,    $zoom)),
            'address' => function_exists('sanitize_text_field')
                ? sanitize_text_field($addr)
                : htmlspecialchars(strip_tags(trim($addr)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
}
