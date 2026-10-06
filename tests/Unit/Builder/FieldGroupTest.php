<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Builder;

use CtrlField\Builder\AdminContext;
use CtrlField\Builder\Exceptions\DuplicateGroupKeyException;
use CtrlField\Builder\Exceptions\InvalidConditionException;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class FieldGroupTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    // -------------------------------------------------------------------------
    // Builder API
    // -------------------------------------------------------------------------

    public function test_make_returns_new_instance(): void
    {
        $group = FieldGroup::make('test');

        $this->assertInstanceOf(FieldGroup::class, $group);
        $this->assertSame('test', $group->getKey());
    }

    public function test_title_sets_title(): void
    {
        $group = FieldGroup::make('g')->title('My Group');

        $this->assertSame('My Group', $group->getTitle());
    }

    public function test_fields_stores_field_definitions(): void
    {
        $group = FieldGroup::make('g')->fields([
            Field::text('name'),
            Field::email('email'),
        ]);

        $this->assertCount(2, $group->getFields());
    }

    public function test_where_adds_and_condition(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'portfolio');

        $conditions = $group->getAndConditions();

        $this->assertCount(1, $conditions);
        $this->assertSame('post_type', $conditions[0]['key']);
        $this->assertSame('==', $conditions[0]['operator']);
        $this->assertSame('portfolio', $conditions[0]['value']);
    }

    public function test_chained_where_adds_multiple_and_conditions(): void
    {
        $group = FieldGroup::make('g')
            ->where('post_type', '==', 'portfolio')
            ->where('post_type', '!=', 'page');

        $this->assertCount(2, $group->getAndConditions());
    }

    public function test_where_any_adds_or_group(): void
    {
        $group = FieldGroup::make('g')->whereAny([
            ['post_type', '==', 'portfolio'],
            ['post_type', '==', 'service'],
        ]);

        $orGroups = $group->getOrGroups();

        $this->assertCount(1, $orGroups);
        $this->assertCount(2, $orGroups[0]);
    }

    public function test_where_rejects_invalid_operator(): void
    {
        $this->expectException(InvalidConditionException::class);
        $this->expectExceptionMessage("Invalid where() operator");

        FieldGroup::make('g')->where('post_type', '===', 'portfolio');
    }

    public function test_where_any_rejects_invalid_operator(): void
    {
        $this->expectException(InvalidConditionException::class);

        FieldGroup::make('g')->whereAny([['post_type', '~=', 'portfolio']]);
    }

    public function test_fluent_methods_return_same_instance(): void
    {
        $group = FieldGroup::make('g');

        $this->assertSame($group, $group->title('T'));
        $this->assertSame($group, $group->where('post_type', '==', 'p'));
        $this->assertSame($group, $group->whereAny([['post_type', '==', 'p']]));
        $this->assertSame($group, $group->fields([]));
    }

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    public function test_register_adds_group_to_field_registry(): void
    {
        FieldGroup::make('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('client_name')])
            ->register();

        $this->assertTrue(FieldRegistry::has('portfolio_details'));
        $this->assertSame('portfolio_details', FieldRegistry::get('portfolio_details')->getKey());
    }

    public function test_acceptance_criteria_field_group_via_field_factory(): void
    {
        // Exact acceptance criteria from CYCLES.md Cycle 2:
        // Field::group('test')->where('post_type', '==', 'portfolio')->fields([...])->register()
        // adds to FieldRegistry, and FieldRegistry::get('test') returns the complete FieldGroup.
        Field::group('test')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
            ])
            ->register();

        $group = FieldRegistry::get('test');
        $this->assertSame('test', $group->getKey());
        $this->assertCount(1, $group->getFields());

        $resolved = ContextRegistry::resolve(new AdminContext(postType: 'portfolio'));
        $this->assertArrayHasKey('test', $resolved);
    }

    public function test_register_duplicate_key_throws(): void
    {
        FieldGroup::make('same_key')->where('post_type', '==', 'portfolio')->register();

        $this->expectException(DuplicateGroupKeyException::class);

        FieldGroup::make('same_key')->where('post_type', '==', 'service')->register();
    }

    // -------------------------------------------------------------------------
    // Context matching
    // -------------------------------------------------------------------------

    public function test_no_conditions_never_matches(): void
    {
        $group = FieldGroup::make('g');

        $this->assertFalse($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertFalse($group->matches(new AdminContext()));
    }

    public function test_matches_post_type_equal(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '==', 'portfolio');

        $this->assertTrue($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'page')));
        $this->assertFalse($group->matches(new AdminContext()));
    }

    public function test_matches_post_type_not_equal(): void
    {
        $group = FieldGroup::make('g')->where('post_type', '!=', 'page');

        $this->assertTrue($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'page')));
    }

    public function test_matches_options_page(): void
    {
        $group = FieldGroup::make('g')->where('options_page', '==', 'theme_settings');

        $this->assertTrue($group->matches(new AdminContext(optionsPage: 'theme_settings')));
        $this->assertFalse($group->matches(new AdminContext(optionsPage: 'other_page')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'portfolio')));
    }

    public function test_multiple_and_conditions_all_must_match(): void
    {
        $group = FieldGroup::make('g')
            ->where('post_type', '==', 'portfolio')
            ->where('post_type', '!=', 'page');

        $this->assertTrue($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'page')));
    }

    public function test_where_any_or_logic(): void
    {
        $group = FieldGroup::make('g')->whereAny([
            ['post_type', '==', 'portfolio'],
            ['post_type', '==', 'service'],
        ]);

        $this->assertTrue($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertTrue($group->matches(new AdminContext(postType: 'service')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'page')));
    }

    public function test_where_and_where_any_combined(): void
    {
        // AND: post_type must be portfolio
        // OR group: post_type is portfolio OR service (at least one must match)
        // Net result: only 'portfolio' satisfies BOTH the AND condition and the OR group.
        // 'service' satisfies the OR group but fails the AND condition.
        $group = FieldGroup::make('g')
            ->where('post_type', '==', 'portfolio')
            ->whereAny([
                ['post_type', '==', 'portfolio'],
                ['post_type', '==', 'service'],
            ]);

        $this->assertTrue($group->matches(new AdminContext(postType: 'portfolio')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'service')));
        $this->assertFalse($group->matches(new AdminContext(postType: 'page')));
    }

    public function test_multiple_where_any_calls_create_independent_or_groups(): void
    {
        // Each whereAny() adds an independent OR group.
        // ALL OR groups must individually pass for the match to succeed.
        // This creates a condition that is impossible to satisfy (no post_type can be
        // both 'portfolio' and 'service' simultaneously via the same context key).
        $group = FieldGroup::make('g')
            ->whereAny([
                ['post_type', '==', 'portfolio'],
                ['post_type', '==', 'service'],
            ])
            ->whereAny([
                ['options_page', '==', 'theme_settings'],
            ]);

        // Satisfies first OR group (portfolio) but not second (no options_page) → false
        $this->assertFalse($group->matches(new AdminContext(postType: 'portfolio')));

        // Satisfies second OR group but not first → false
        $this->assertFalse($group->matches(new AdminContext(optionsPage: 'theme_settings')));

        // Both OR groups satisfied simultaneously → true
        $this->assertTrue($group->matches(
            new AdminContext(postType: 'portfolio', optionsPage: 'theme_settings')
        ));
    }

    public function test_where_any_throws_on_empty_conditions(): void
    {
        $this->expectException(InvalidConditionException::class);
        $this->expectExceptionMessage('at least one condition');

        FieldGroup::make('g')->whereAny([]);
    }

    public function test_unknown_context_key_throws(): void
    {
        // v2: unknown where() keys throw immediately instead of evaluating to null
        $this->expectException(InvalidConditionException::class);
        $this->expectExceptionMessage("Unknown context key 'unknown_key'");

        FieldGroup::make('g')->where('unknown_key', '==', 'value');
    }

    // Layout settings
    public function test_position_defaults_normal(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([]);
        $this->assertSame('normal', $group->getPosition());
    }

    public function test_position_can_be_set_to_side(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([])->position('side');
        $this->assertSame('side', $group->getPosition());
    }

    public function test_position_after_title(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([])->position('after_title');
        $this->assertSame('after_title', $group->getPosition());
    }

    public function test_style_defaults_default(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([]);
        $this->assertSame('default', $group->getStyle());
    }

    public function test_style_seamless(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([])->style('seamless');
        $this->assertSame('seamless', $group->getStyle());
    }

    public function test_label_placement_defaults_top(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([]);
        $this->assertSame('top', $group->getLabelPlacement());
    }

    public function test_label_placement_left(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([])->labelPlacement('left');
        $this->assertSame('left', $group->getLabelPlacement());
    }

    public function test_instruction_placement_defaults_label(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([]);
        $this->assertSame('label', $group->getInstructionPlacement());
    }

    public function test_instruction_placement_field(): void
    {
        $group = Field::group('g')->where('post_type', '==', 'post')->fields([])->instructionPlacement('field');
        $this->assertSame('field', $group->getInstructionPlacement());
    }
}
