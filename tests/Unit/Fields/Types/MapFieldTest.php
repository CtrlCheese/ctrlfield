<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\Types\MapField;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

class MapFieldTest extends TestCase
{
    // -------------------------------------------------------------------------
    // AC 9 — basic API
    // -------------------------------------------------------------------------

    public function testProFieldFactoryReturnsInstance(): void
    {
        $field = Field::map('location');
        self::assertInstanceOf(MapField::class, $field);
    }

    public function testFieldType(): void
    {
        $field = new MapField('location');
        self::assertSame(FieldType::MAP, $field->getType());
    }

    public function testImplementsFieldSanitizerInterface(): void
    {
        $field = new MapField('location');
        self::assertInstanceOf(FieldSanitizerInterface::class, $field);
    }

    public function testFluentChain(): void
    {
        $field = (new MapField('office'))
            ->provider('google')
            ->geocoder(true)
            ->defaultLat(48.8566)
            ->defaultLng(2.3522)
            ->zoom(14)
            ->height(500);

        self::assertSame('google', $field->getProvider());
        self::assertTrue($field->hasGeocoder());
        self::assertSame(48.8566, $field->getDefaultLat());
        self::assertSame(2.3522, $field->getDefaultLng());
        self::assertSame(14, $field->getZoom());
    }

    // -------------------------------------------------------------------------
    // AC 14 — invalid provider throws at definition time
    // -------------------------------------------------------------------------

    public function testInvalidProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Invalid map provider 'invalid'/");

        (new MapField('office'))->provider('invalid');
    }

    public function testAllValidProvidersAccepted(): void
    {
        foreach (['google', 'mapbox', 'openstreetmap'] as $provider) {
            $field = (new MapField('office'))->provider($provider);
            self::assertSame($provider, $field->getProvider());
        }
    }

    // -------------------------------------------------------------------------
    // AC 10 — correct JSON storage shape
    // -------------------------------------------------------------------------

    public function testSanitizeForStorageReturnsCorrectShape(): void
    {
        $field  = new MapField('location');
        $result = $field->sanitizeForStorage(['lat' => 48.8566, 'lng' => 2.3522, 'zoom' => 14, 'address' => 'Paris']);

        self::assertSame(48.8566, $result['lat']);
        self::assertSame(2.3522, $result['lng']);
        self::assertSame(14, $result['zoom']);
        self::assertSame('Paris', $result['address']);
    }

    public function testSanitizeReturnsEmptyForMissingValue(): void
    {
        // No coordinates = no location; 0,0 is a real place, never a default.
        self::assertSame([], (new MapField('location'))->sanitizeForStorage(null));
    }

    // -------------------------------------------------------------------------
    // zoom clamping
    // -------------------------------------------------------------------------

    public function testZoomClampedTo1To20(): void
    {
        self::assertSame(1,  (new MapField('m'))->zoom(-5)->getZoom());
        self::assertSame(20, (new MapField('m'))->zoom(99)->getZoom());
        self::assertSame(12, (new MapField('m'))->zoom(12)->getZoom());
    }

    public function testGetDefinitionStructure(): void
    {
        $field = (new MapField('location'))->provider('mapbox')->geocoder(false)->label('Location');
        $def   = $field->getDefinition();

        self::assertSame('map', $def['type']);
        self::assertSame('mapbox', $def['provider']);
        self::assertFalse($def['geocoder']);
    }
}
