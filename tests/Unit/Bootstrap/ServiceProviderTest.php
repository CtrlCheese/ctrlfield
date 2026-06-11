<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Bootstrap;

use FieldForge\Bootstrap\ServiceContainer;
use FieldForge\Bootstrap\ServiceProvider;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_boot_is_a_no_op_by_default(): void
    {
        $container = new ServiceContainer();
        $provider  = new ConcreteProviderStub($container);

        // boot() must not throw and must return void
        $result = $provider->boot();

        $this->assertNull($result);
    }

    public function test_register_is_called_and_can_bind_to_container(): void
    {
        $container = new ServiceContainer();
        $provider  = new ConcreteProviderStub($container);

        $provider->register();

        $this->assertTrue($container->has('registered_service'));
    }

    public function test_container_is_accessible_inside_provider(): void
    {
        $container = new ServiceContainer();
        $provider  = new ConcreteProviderStub($container);

        $this->assertSame($container, $provider->exposeContainer());
    }
}

// ---------------------------------------------------------------------------
// Stub
// ---------------------------------------------------------------------------

class ConcreteProviderStub extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind('registered_service', \stdClass::class);
    }

    public function exposeContainer(): ServiceContainer
    {
        return $this->container;
    }
}
