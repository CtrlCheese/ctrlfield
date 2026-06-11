<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Admin\Dashboard;

use FieldForge\Bootstrap\ServiceContainer;
use FieldForge\Admin\Dashboard\DashboardWidgetServiceProvider;
use FieldForge\Builder\DashboardWidgetConfig;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class DashboardWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_dashboard_widget_fluent_stores_config(): void
    {
        $group = Field::group('quick_settings')
            ->where('context', '==', 'dashboard_widget')
            ->dashboardWidget('Site Quick Settings', 'side', 'high')
            ->fields([Field::text('mode')->label('Mode')]);
        $group->register();

        $config = $group->getDashboardWidgetConfig();

        $this->assertInstanceOf(DashboardWidgetConfig::class, $config);
        $this->assertSame('Site Quick Settings', $config->title);
        $this->assertSame('side', $config->context);
        $this->assertSame('high', $config->priority);
    }

    public function test_is_dashboard_widget_false_by_default(): void
    {
        $group = Field::group('normal_group')
            ->where('post_type', '==', 'post')
            ->fields([Field::text('name')->label('Name')]);
        $group->register();

        $this->assertFalse($group->isDashboardWidget());
    }

    public function test_is_dashboard_widget_true_when_configured(): void
    {
        $group = Field::group('widget_group')
            ->where('context', '==', 'dashboard_widget')
            ->dashboardWidget('My Widget')
            ->fields([Field::text('setting')->label('Setting')]);
        $group->register();

        $this->assertTrue($group->isDashboardWidget());
    }

    public function test_dashboard_widget_config_defaults(): void
    {
        $group = Field::group('widget_defaults')
            ->where('context', '==', 'dashboard_widget')
            ->dashboardWidget('Default Widget')
            ->fields([Field::text('opt')->label('Option')]);
        $group->register();

        $config = $group->getDashboardWidgetConfig();
        $this->assertSame('normal', $config->context);
        $this->assertSame('default', $config->priority);
    }

    public function test_provider_boot_without_wp_does_not_throw(): void
    {
        $provider = new DashboardWidgetServiceProvider(new ServiceContainer());
        $provider->boot();

        $this->addToAssertionCount(1);
    }
}
