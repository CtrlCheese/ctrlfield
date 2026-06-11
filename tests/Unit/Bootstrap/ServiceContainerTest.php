<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Bootstrap;

use FieldForge\Bootstrap\Exceptions\ContainerException;
use FieldForge\Bootstrap\Exceptions\NotFoundException;
use FieldForge\Bootstrap\ServiceContainer;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
    }

    public function test_make_resolves_concrete_class_with_no_constructor(): void
    {
        $instance = $this->container->make(NoDepsStub::class);

        $this->assertInstanceOf(NoDepsStub::class, $instance);
    }

    public function test_make_resolves_class_with_typed_dependencies(): void
    {
        $instance = $this->container->make(HasDepsStub::class);

        $this->assertInstanceOf(HasDepsStub::class, $instance);
        $this->assertInstanceOf(NoDepsStub::class, $instance->dep);
    }

    public function test_bind_returns_new_instance_on_each_call(): void
    {
        $this->container->bind(NoDepsStub::class, NoDepsStub::class);

        $a = $this->container->make(NoDepsStub::class);
        $b = $this->container->make(NoDepsStub::class);

        $this->assertNotSame($a, $b);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton(NoDepsStub::class, NoDepsStub::class);

        $a = $this->container->make(NoDepsStub::class);
        $b = $this->container->make(NoDepsStub::class);

        $this->assertSame($a, $b);
    }

    public function test_instance_returns_registered_object(): void
    {
        $stub = new NoDepsStub();
        $this->container->instance(NoDepsStub::class, $stub);

        $this->assertSame($stub, $this->container->make(NoDepsStub::class));
    }

    public function test_bind_accepts_closure(): void
    {
        $this->container->bind('custom_key', fn() => new NoDepsStub());

        $this->assertInstanceOf(NoDepsStub::class, $this->container->make('custom_key'));
    }

    public function test_psr11_get_delegates_to_make(): void
    {
        $instance = $this->container->get(NoDepsStub::class);

        $this->assertInstanceOf(NoDepsStub::class, $instance);
    }

    public function test_psr11_has_returns_true_for_existing_class(): void
    {
        $this->assertTrue($this->container->has(NoDepsStub::class));
    }

    public function test_psr11_has_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->container->has('NonExistentClass_XYZ'));
    }

    public function test_psr11_get_throws_not_found_for_unknown_id(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('NonExistentClass_XYZ');
    }

    public function test_make_throws_container_exception_for_non_instantiable_class(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->make(AbstractStub::class);
    }

    public function test_make_throws_container_exception_for_unresolvable_primitive(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->make(UnresolvablePrimitiveStub::class);
    }
}

// ---------------------------------------------------------------------------
// Stubs (local to this test file)
// ---------------------------------------------------------------------------

class NoDepsStub {}

class HasDepsStub
{
    public function __construct(public readonly NoDepsStub $dep) {}
}

abstract class AbstractStub {}

class UnresolvablePrimitiveStub
{
    public function __construct(private string $name) {}
}
