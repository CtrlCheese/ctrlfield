<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Fields;

use FieldForge\Fields\Exceptions\PresetNotFoundException;
use FieldForge\Fields\Field;
use FieldForge\Fields\FieldDefinition;
use FieldForge\Fields\FieldPresets;
use PHPUnit\Framework\TestCase;

class FieldPresetsTest extends TestCase
{
    protected function setUp(): void
    {
        FieldPresets::reset();
    }

    protected function tearDown(): void
    {
        FieldPresets::reset();
    }

    public function test_register_and_has(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme'));

        $this->assertTrue(FieldPresets::has('theme'));
        $this->assertFalse(FieldPresets::has('nonexistent'));
    }

    public function test_get_returns_field_definition(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme')->options(['light' => 'Light']));

        $result = FieldPresets::get('theme');

        $this->assertInstanceOf(FieldDefinition::class, $result);
    }

    public function test_get_returns_fresh_instance_per_call(): void
    {
        FieldPresets::register('size', fn () => Field::select('size')->options(['sm' => 'Small']));

        $a = FieldPresets::get('size');
        $b = FieldPresets::get('size');

        $this->assertNotSame($a, $b);
    }

    public function test_get_throws_for_unregistered_preset(): void
    {
        $this->expectException(PresetNotFoundException::class);
        $this->expectExceptionMessage("FieldPreset 'missing' is not registered");

        FieldPresets::get('missing');
    }

    public function test_magic_callstatic_delegates_to_get(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme'));

        /** @var FieldDefinition $result */
        $result = FieldPresets::theme();

        $this->assertInstanceOf(FieldDefinition::class, $result);
    }

    public function test_magic_callstatic_throws_for_unregistered(): void
    {
        $this->expectException(PresetNotFoundException::class);

        FieldPresets::nonexistent();
    }

    public function test_keys_returns_registered_names(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme'));
        FieldPresets::register('size', fn () => Field::select('size'));

        $keys = FieldPresets::keys();

        $this->assertContains('theme', $keys);
        $this->assertContains('size', $keys);
        $this->assertCount(2, $keys);
    }

    public function test_keys_empty_after_reset(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme'));
        FieldPresets::reset();

        $this->assertEmpty(FieldPresets::keys());
    }

    public function test_distinct_instances_are_independent(): void
    {
        FieldPresets::register('color', fn () => Field::color('brand_color'));

        $a = FieldPresets::get('color');
        $b = FieldPresets::get('color');

        $a->label('First');
        $b->label('Second');

        $this->assertNotSame($a->getDefinition()['label'], $b->getDefinition()['label']);
    }

    public function test_multiple_presets_coexist(): void
    {
        FieldPresets::register('theme', fn () => Field::select('theme')->options(['light' => 'Light']));
        FieldPresets::register('spacing', fn () => Field::select('spacing')->options(['none' => 'None', 'sm' => 'Small']));

        $theme   = FieldPresets::get('theme');
        $spacing = FieldPresets::get('spacing');

        $this->assertSame(['light' => 'Light'], $theme->getDefinition()['options']);
        $this->assertSame(['none' => 'None', 'sm' => 'Small'], $spacing->getDefinition()['options']);
    }
}
