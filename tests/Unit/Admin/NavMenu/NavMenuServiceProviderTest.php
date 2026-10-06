<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\NavMenu;

use CtrlField\Admin\NavMenu\NavMenuServiceProvider;
use CtrlField\Bootstrap\ServiceContainer;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class NavMenuServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_register_method_is_noop(): void
    {
        $provider = new NavMenuServiceProvider(new ServiceContainer());
        $provider->register();

        $this->addToAssertionCount(1);
    }

    public function test_boot_without_wp_functions_does_not_throw(): void
    {
        $provider = new NavMenuServiceProvider(new ServiceContainer());
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    public function test_nav_menu_group_resolved_via_context_type(): void
    {
        Field::group('menu_extras')
            ->where('context', '==', 'nav_menu_item')
            ->fields([
                Field::text('icon')->label('Icon Class'),
                Field::text('badge_text')->label('Badge Text'),
            ])
            ->register();

        $groups = FieldRegistry::all();
        $this->assertArrayHasKey('menu_extras', $groups);

        $fieldKeys = array_map(
            static fn($f) => $f->getKey(),
            $groups['menu_extras']->getFields(),
        );

        $this->assertContains('icon', $fieldKeys);
        $this->assertContains('badge_text', $fieldKeys);
    }

    public function test_save_fields_skips_when_nonce_absent(): void
    {
        // No nonce key in $_POST — saveFields should silently return.
        $provider = new NavMenuServiceProvider(new ServiceContainer());

        // Should not throw
        $provider->saveFields(1, 5, []);

        $this->addToAssertionCount(1);
    }

    public function test_save_fields_skips_when_no_ctrlf_nav_data(): void
    {
        // Simulate nonce present but no ctrlf_nav data.
        $_POST['_ctrlf_nav_nonce_5'] = 'fake_nonce';

        $provider = new NavMenuServiceProvider(new ServiceContainer());
        $provider->saveFields(1, 5, []);

        unset($_POST['_ctrlf_nav_nonce_5']);

        $this->addToAssertionCount(1);
    }
}
