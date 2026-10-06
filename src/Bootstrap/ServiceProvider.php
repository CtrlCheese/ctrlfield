<?php

declare(strict_types=1);

namespace CtrlField\Bootstrap;

abstract class ServiceProvider
{
    public function __construct(
        protected readonly ServiceContainer $container
    ) {}

    abstract public function register(): void;

    public function boot(): void {}
}
