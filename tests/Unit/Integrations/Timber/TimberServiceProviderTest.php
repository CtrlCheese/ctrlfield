<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\Timber;

use CtrlField\Bootstrap\ServiceContainer;
use CtrlField\Integrations\Timber\TimberServiceProvider;
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
