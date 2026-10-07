<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Sanitizers;

use CtrlField\Fields\Sanitizers\MapSanitizer;
use PHPUnit\Framework\TestCase;

class MapSanitizerTest extends TestCase
{
    private MapSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new MapSanitizer();
    }

    // -------------------------------------------------------------------------
    // AC 11 — lat clamping
    // -------------------------------------------------------------------------

    public function testClampsLatAbove90(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => 91.0, 'lng' => 0.0, 'zoom' => 14, 'address' => '']);
        self::assertSame(90.0, $result['lat']);
    }

    public function testClampsLatBelow90(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => -91.0, 'lng' => 0.0, 'zoom' => 14, 'address' => '']);
        self::assertSame(-90.0, $result['lat']);
    }

    public function testClampsLngAbove180(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => 0.0, 'lng' => 181.0, 'zoom' => 14, 'address' => '']);
        self::assertSame(180.0, $result['lng']);
    }

    public function testClampsLngBelow180(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => 0.0, 'lng' => -181.0, 'zoom' => 14, 'address' => '']);
        self::assertSame(-180.0, $result['lng']);
    }

    public function testClampsZoomMin1(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => 0.0, 'lng' => 0.0, 'zoom' => 0, 'address' => '']);
        self::assertSame(1, $result['zoom']);
    }

    public function testClampsZoomMax20(): void
    {
        $result = $this->sanitizer->sanitize(['lat' => 0.0, 'lng' => 0.0, 'zoom' => 25, 'address' => '']);
        self::assertSame(20, $result['zoom']);
    }

    public function testPreservesValidValues(): void
    {
        $input  = ['lat' => 48.8566, 'lng' => 2.3522, 'zoom' => 14, 'address' => 'Paris, France'];
        $result = $this->sanitizer->sanitize($input);

        self::assertSame(48.8566, $result['lat']);
        self::assertSame(2.3522,  $result['lng']);
        self::assertSame(14,      $result['zoom']);
        self::assertSame('Paris, France', $result['address']);
    }

    public function testHandlesJsonStringInput(): void
    {
        $json   = json_encode(['lat' => 40.7128, 'lng' => -74.0060, 'zoom' => 12, 'address' => 'New York']);
        $result = $this->sanitizer->sanitize($json);

        self::assertSame(40.7128,  $result['lat']);
        self::assertSame(-74.0060, $result['lng']);
        self::assertSame(12,       $result['zoom']);
    }

    public function testReturnsEmptyForNull(): void
    {
        // No coordinates = no location; 0,0 is a real place, never a default.
        self::assertSame([], $this->sanitizer->sanitize(null));
    }

    public function testReturnsEmptyForEmptyArray(): void
    {
        // No coordinates = no location; 0,0 is a real place, never a default.
        self::assertSame([], $this->sanitizer->sanitize([]));
    }

    public function testOneMissingCoordinateMeansNoLocation(): void
    {
        self::assertSame([], $this->sanitizer->sanitize(['lat' => 52.5, 'lng' => null, 'address' => 'Berlin']));
    }
}
