<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Compat;

use CtrlField\Compat\Acf\AcfApi;
use CtrlField\Compat\Acf\AcfValues;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

/** acf_add_local_field_group() / acf_add_local_field() as Flynt uses them. */
final class AcfLocalGroupsTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
        AcfApi::resetLocalGroups();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
        AcfApi::resetLocalGroups();
    }

    private function group(): array
    {
        return [
            'key'      => 'group_GlobalOptions_Default',
            'title'    => 'Global Options',
            'fields'   => [],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]],
        ];
    }

    public function test_fields_added_later_are_part_of_the_group(): void
    {
        AcfApi::addLocalFieldGroup($this->group());
        AcfApi::addLocalField([
            'parent' => 'group_GlobalOptions_Default',
            'key'    => 'field_global_Resize_dynamicImageGeneration',
            'name'   => 'Resize_dynamicImageGeneration',
            'type'   => 'true_false',
        ]);

        $this->assertFalse(FieldRegistry::has('group_GlobalOptions_Default'), 'registered on flush, not before');

        AcfApi::flushLocalGroups();

        $fields = FieldRegistry::get('group_GlobalOptions_Default')->getFields();
        $this->assertSame('Resize_dynamicImageGeneration', $fields[0]->getKey(), 'camelCase kept');
        $this->assertSame('Resize_dynamicImageGeneration', AcfApi::resolveKey('field_global_Resize_dynamicImageGeneration'));
    }

    public function test_a_field_added_after_the_flush_re_registers_the_group(): void
    {
        AcfApi::addLocalFieldGroup($this->group());
        AcfApi::flushLocalGroups();
        AcfApi::addLocalField(['parent' => 'group_GlobalOptions_Default', 'key' => 'field_x', 'name' => 'late', 'type' => 'text']);

        $this->assertSame('late', FieldRegistry::get('group_GlobalOptions_Default')->getFields()[0]->getKey());
    }

    public function test_unknown_parent_is_refused(): void
    {
        $this->assertFalse(AcfApi::addLocalField(['parent' => 'group_nope', 'name' => 'x', 'type' => 'text']));
    }

    public function test_code_groups_win(): void
    {
        \CtrlField\Builder\FieldGroup::make('group_GlobalOptions_Default')
            ->where('post_type', '==', 'post')->fields([Field::text('mine')])->register();

        AcfApi::addLocalFieldGroup(array_merge($this->group(), ['fields' => [['key' => 'f', 'name' => 'theirs', 'type' => 'text']]]));
        AcfApi::flushLocalGroups();

        $this->assertSame('mine', FieldRegistry::get('group_GlobalOptions_Default')->getFields()[0]->getKey());
    }

    public function test_unsaved_values_return_defaults_like_acf(): void
    {
        $this->assertSame('Next Slide', AcfValues::format(null, Field::text('next')->default('Next Slide')));
        $this->assertNull(AcfValues::format(null, Field::text('none')));

        $a11y = Field::object('a11y')->fields([
            Field::text('next')->default('Next Slide'),
            Field::text('prev'),
        ]);
        $this->assertSame(['next' => 'Next Slide', 'prev' => null], AcfValues::format(null, $a11y), 'a group never saved still has its defaults');
    }
}
