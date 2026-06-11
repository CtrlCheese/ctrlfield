<?php

declare(strict_types=1);

namespace FieldForge\Bootstrap;

use Closure;
use FieldForge\Bootstrap\Exceptions\ContainerException;
use FieldForge\Bootstrap\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

class ServiceContainer implements ContainerInterface
{
    /** @var array<string, Closure|string> */
    private array $bindings = [];

    /** @var array<string, Closure|string> */
    private array $singletonBindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->singletonBindings[$abstract] = $concrete;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletonBindings[$abstract])) {
            $this->instances[$abstract] = $this->resolve($this->singletonBindings[$abstract]);
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolve($this->bindings[$abstract]);
        }

        return $this->build($abstract);
    }

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new NotFoundException("No binding found for [{$id}].");
        }

        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->singletonBindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    private function resolve(Closure|string $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        return $this->build($concrete);
    }

    private function build(string $class): mixed
    {
        if (! class_exists($class)) {
            throw new NotFoundException("Class [{$class}] does not exist.");
        }

        $reflector = new ReflectionClass($class);

        if (! $reflector->isInstantiable()) {
            throw new ContainerException("Class [{$class}] is not instantiable. Is it abstract or an interface?");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveDependency($param, $class),
            $constructor->getParameters()
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveDependency(ReflectionParameter $param, string $parentClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new ContainerException(
            "Unable to resolve dependency [\${$param->getName()}] in [{$parentClass}]. "
            . "No type hint or default value available."
        );
    }
}
