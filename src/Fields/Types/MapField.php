<?php

declare(strict_types=1);

namespace CtrlField\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\FieldDefinition;
use CtrlField\Fields\Sanitizers\MapSanitizer;
use InvalidArgumentException;

final class MapField extends FieldDefinition implements FieldSanitizerInterface
{
    private const VALID_PROVIDERS = ['google', 'mapbox', 'openstreetmap'];

    private string $provider    = 'google';
    private ?float $defaultLat  = null;
    private ?float $defaultLng  = null;
    private int    $zoom        = 14;
    private bool   $geocoder    = true;
    private bool   $draggable   = true;
    private int    $height      = 400;

    public function getType(): FieldType
    {
        return FieldType::MAP;
    }

    public function provider(string $provider): static
    {
        if (! in_array($provider, self::VALID_PROVIDERS, true)) {
            throw new InvalidArgumentException(
                "Invalid map provider '{$provider}'. Valid providers: " . implode(', ', self::VALID_PROVIDERS) . '.'
            );
        }
        $this->provider = $provider;
        return $this;
    }

    public function defaultLat(float $lat): static
    {
        $this->defaultLat = $lat;
        return $this;
    }

    public function defaultLng(float $lng): static
    {
        $this->defaultLng = $lng;
        return $this;
    }

    public function zoom(int $zoom): static
    {
        $this->zoom = max(1, min(20, $zoom));
        return $this;
    }

    public function geocoder(bool $enable = true): static
    {
        $this->geocoder = $enable;
        return $this;
    }

    public function draggable(bool $enable = true): static
    {
        $this->draggable = $enable;
        return $this;
    }

    public function height(int $pixels): static
    {
        $this->height = max(100, $pixels);
        return $this;
    }

    // -------------------------------------------------------------------------
    // FieldSanitizerInterface
    // -------------------------------------------------------------------------

    public function sanitizeForStorage(mixed $value): mixed
    {
        return (new MapSanitizer())->sanitize($value);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getDefaultLat(): ?float
    {
        return $this->defaultLat;
    }

    public function getDefaultLng(): ?float
    {
        return $this->defaultLng;
    }

    public function getZoom(): int
    {
        return $this->zoom;
    }

    public function hasGeocoder(): bool
    {
        return $this->geocoder;
    }

    /** @return array<string, mixed> */
    public function getDefinition(): array
    {
        return array_merge(parent::getDefinition(), [
            'provider'    => $this->provider,
            'default_lat' => $this->defaultLat,
            'default_lng' => $this->defaultLng,
            'zoom'        => $this->zoom,
            'geocoder'    => $this->geocoder,
            'draggable'   => $this->draggable,
            'height'      => $this->height,
        ]);
    }
}
