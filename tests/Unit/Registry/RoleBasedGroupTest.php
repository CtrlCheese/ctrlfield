<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Builder\AdminContext;
use CtrlField\Fields\Field;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class RoleBasedGroupTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_required_capability_fluent_stores_value(): void
    {
        $group = Field::group('admin_notes')
            ->where('post_type', '==', 'portfolio')
            ->requiredCapability('manage_options')
            ->fields([Field::text('note')->label('Note')]);
        $group->register();

        $this->assertSame('manage_options', $group->getRequiredCapability());
    }

    public function test_group_without_capability_is_visible_to_all(): void
    {
        Field::group('public_group')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('name')->label('Name')])
            ->register();

        $context = new AdminContext(postType: 'portfolio');
        $groups  = ContextRegistry::resolve($context);

        $this->assertArrayHasKey('public_group', $groups);
    }

    public function test_group_with_capability_is_hidden_when_user_lacks_it(): void
    {
        // current_user_can stub returns false
        Field::group('admin_notes')
            ->where('post_type', '==', 'portfolio')
            ->requiredCapability('manage_options')
            ->fields([Field::text('note')->label('Note')])
            ->register();

        $context = new AdminContext(postType: 'portfolio');
        $groups  = ContextRegistry::resolve($context);

        $this->assertArrayNotHasKey('admin_notes', $groups);
    }

    public function test_empty_required_capability_is_visible(): void
    {
        Field::group('no_cap_group')
            ->where('post_type', '==', 'post')
            ->requiredCapability('')
            ->fields([Field::text('field')->label('Field')])
            ->register();

        $context = new AdminContext(postType: 'post');
        $groups  = ContextRegistry::resolve($context);

        $this->assertArrayHasKey('no_cap_group', $groups);
    }
}
