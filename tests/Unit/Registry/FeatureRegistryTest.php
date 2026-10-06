<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Registry\FeatureRegistry;
use PHPUnit\Framework\TestCase;

class FeatureRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FeatureRegistry::reset();
    }

    public function test_register_and_has(): void
    {
        FeatureRegistry::register('flexible_content', \stdClass::class);

        $this->assertTrue(FeatureRegistry::has('flexible_content'));
    }

    public function test_has_returns_false_for_unknown_feature(): void
    {
        $this->assertFalse(FeatureRegistry::has('nonexistent'));
    }

    public function test_get_returns_registered_class(): void
    {
        FeatureRegistry::register('flexible_content', \stdClass::class);

        $this->assertSame(\stdClass::class, FeatureRegistry::get('flexible_content'));
    }

    public function test_get_returns_null_for_unknown_feature(): void
    {
        $this->assertNull(FeatureRegistry::get('nonexistent'));
    }

    public function test_all_returns_all_features(): void
    {
        FeatureRegistry::register('feature_a', \stdClass::class);
        FeatureRegistry::register('feature_b', \stdClass::class);

        $this->assertCount(2, FeatureRegistry::all());
    }

    public function test_reset_clears_all_features(): void
    {
        FeatureRegistry::register('feature_a', \stdClass::class);
        FeatureRegistry::reset();

        $this->assertEmpty(FeatureRegistry::all());
    }

    public function test_registering_same_feature_twice_overwrites(): void
    {
        FeatureRegistry::register('feature', \stdClass::class);
        FeatureRegistry::register('feature', \ArrayObject::class);

        $this->assertSame(\ArrayObject::class, FeatureRegistry::get('feature'));
    }
}
