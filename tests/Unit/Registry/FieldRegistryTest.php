<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Bootstrap\Exceptions\NotFoundException;
use CtrlField\Builder\Exceptions\DuplicateGroupKeyException;
use CtrlField\Builder\FieldGroup;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class FieldRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    public function test_add_and_get(): void
    {
        $group = FieldGroup::make('test')->where('post_type', '==', 'portfolio');

        FieldRegistry::add($group);

        $this->assertSame($group, FieldRegistry::get('test'));
    }

    public function test_has_returns_false_before_registration(): void
    {
        $this->assertFalse(FieldRegistry::has('nonexistent'));
    }

    public function test_has_returns_true_after_registration(): void
    {
        FieldRegistry::add(FieldGroup::make('test')->where('post_type', '==', 'p'));

        $this->assertTrue(FieldRegistry::has('test'));
    }

    public function test_get_throws_not_found_for_unknown_key(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("'nonexistent'");

        FieldRegistry::get('nonexistent');
    }

    public function test_duplicate_key_throws(): void
    {
        FieldRegistry::add(FieldGroup::make('key')->where('post_type', '==', 'a'));

        $this->expectException(DuplicateGroupKeyException::class);
        $this->expectExceptionMessage("'key'");

        FieldRegistry::add(FieldGroup::make('key')->where('post_type', '==', 'b'));
    }

    public function test_all_returns_all_registered_groups(): void
    {
        FieldRegistry::add(FieldGroup::make('g1')->where('post_type', '==', 'p1'));
        FieldRegistry::add(FieldGroup::make('g2')->where('post_type', '==', 'p2'));

        $all = FieldRegistry::all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('g1', $all);
        $this->assertArrayHasKey('g2', $all);
    }

    public function test_reset_clears_all_groups(): void
    {
        FieldRegistry::add(FieldGroup::make('g')->where('post_type', '==', 'p'));
        FieldRegistry::reset();

        $this->assertEmpty(FieldRegistry::all());
        $this->assertFalse(FieldRegistry::has('g'));
    }

    public function test_registered_group_contains_nested_fields(): void
    {
        FieldGroup::make('portfolio_details')
            ->title('Project Details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                \CtrlField\Fields\Field::text('client_name')->label('Client Name'),
                \CtrlField\Fields\Field::select('project_type')->options(['a' => 'A']),
            ])
            ->register();

        $group = FieldRegistry::get('portfolio_details');

        $this->assertSame('Project Details', $group->getTitle());
        $this->assertCount(2, $group->getFields());
    }
}
