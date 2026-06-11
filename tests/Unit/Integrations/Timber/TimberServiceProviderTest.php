<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Timber;

use FieldForge\Bootstrap\ServiceContainer;
use FieldForge\Integrations\Timber\TimberServiceProvider;
use PHPUnit\Framework\TestCase;

class TimberServiceProviderTest extends TestCase
{
    public function test_register_is_noop(): void
    {
        $provider = new TimberServiceProvider(new ServiceContainer());
        $provider->register();

        $this->addToAssertionCount(1);
    }

    public function test_boot_without_timber_does_not_throw(): void
    {
        // \Timber\Timber is not present in unit test environment.
        $provider = new TimberServiceProvider(new ServiceContainer());
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function test_provider_is_instantiable(): void
    {
        $this->assertInstanceOf(
            TimberServiceProvider::class,
            new TimberServiceProvider(new ServiceContainer()),
        );
    }
}
