<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Bootstrap;

use FieldForge\Bootstrap\BootManager;
use FieldForge\Bootstrap\ServiceContainer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BootManagerTest extends TestCase
{
    public function test_container_throws_before_boot(): void
    {
        // Reset static state via reflection so this test is isolated
        $this->resetBootManager();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FieldForge has not been booted');

        BootManager::container();
    }

    public function test_boot_provides_container_instance(): void
    {
        $this->resetBootManager();

        BootManager::boot();

        $this->assertInstanceOf(ServiceContainer::class, BootManager::container());
    }

    public function test_boot_is_idempotent(): void
    {
        $this->resetBootManager();

        BootManager::boot();
        $first = BootManager::container();

        BootManager::boot();
        $second = BootManager::container();

        $this->assertSame($first, $second);
    }

    public function test_is_booted_reflects_state(): void
    {
        $this->resetBootManager();
        $this->assertFalse(BootManager::isBooted());

        BootManager::boot();
        $this->assertTrue(BootManager::isBooted());
    }

    public function test_container_registers_itself(): void
    {
        $this->resetBootManager();
        BootManager::boot();

        $resolved = BootManager::container()->get(ServiceContainer::class);

        $this->assertSame(BootManager::container(), $resolved);
    }

    private function resetBootManager(): void
    {
        $ref = new \ReflectionClass(BootManager::class);

        $containerProp = $ref->getProperty('container');
        $containerProp->setValue(null, null);

        $providersProp = $ref->getProperty('bootedProviders');
        $providersProp->setValue(null, []);

        $bootedProp = $ref->getProperty('booted');
        $bootedProp->setValue(null, false);
    }
}
