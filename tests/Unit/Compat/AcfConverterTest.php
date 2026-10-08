<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Compat;

use CtrlField\Compat\Acf\AcfConverter;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

final class AcfConverterTest extends TestCase
{
    /** @param list<array<string, mixed>> $fields */
    private function convert(array $fields, ?array $location = null, array $extra = []): array
    {
        return (new AcfConverter())->convertGroup($extra + [
            'key'      => 'group_1',
            'title'    => 'Group',
            'fields'   => $fields,
            'location' => $location ?? [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ], 'group_one');
    }

    public function test_keys_follow_acf_names(): void
    {
        $this->assertSame('hero_Title', AcfConverter::keyFor('hero-Title'));
        $this->assertSame('contentHtml', AcfConverter::keyFor('contentHtml'), 'camelCase is kept');
        $this->assertSame('f_1st', AcfConverter::keyFor('1st'));
        $this->assertSame('a_b', AcfConverter::keyFor('__a  b__'));
    }

    public function test_basic_fields_and_settings(): void
    {
        $r = $this->convert([
            ['key' => 'field_1', 'name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => 1,
                'instructions' => 'Help', 'default_value' => 'x', 'wrapper' => ['width' => '33']],
            ['key' => 'field_2', 'name' => 'kind', 'label' => 'Kind', 'type' => 'select',
                'choices' => ['a' => 'Alpha', 'b' => 'Beta'], 'return_format' => 'label'],
        ], null, ['position' => 'acf_after_title', 'style' => 'seamless', 'label_placement' => 'left']);

        $g = $r['group'];
        $this->assertSame('after_title', $g['position']);
        $this->assertSame('seamless', $g['style']);
        $this->assertSame('left', $g['labelPlacement']);
        $this->assertSame(
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'instructions' => 'Help', 'default' => 'x', 'width' => 25],
            $g['fields'][0]
        );
        $this->assertSame([['a', 'Alpha'], ['b', 'Beta']], $g['fields'][1]['options']);
        $this->assertSame('label', $g['fields'][1]['returnFormat']);
        $this->assertSame(['field_1' => 'title', 'field_2' => 'kind'], $r['fieldKeys']);
    }

    public function test_renamed_and_unsupported_fields_are_reported(): void
    {
        $r = $this->convert([
            ['key' => 'field_1', 'name' => 'Hero-Title', 'label' => 'Hero', 'type' => 'text'],
            ['key' => 'field_2', 'name' => 'c', 'label' => 'Clone', 'type' => 'clone'],
            ['key' => 'field_3', 'name' => '', 'label' => 'Acc', 'type' => 'accordion'],
        ]);

        $this->assertSame(['text', 'accordion'], array_column($r['group']['fields'], 'type'), 'clone skipped, accordion kept');
        $this->assertTrue($r['group']['fields'][1]['closed'], 'ACF accordions start closed');
        $this->assertSame(['Hero-Title' => 'Hero_Title'], $r['names']);
        $this->assertCount(2, $r['warnings'], 'rename + unsupported clone');
    }

    public function test_type_specific_settings(): void
    {
        $f = $this->convert([
            ['key' => 'k1', 'name' => 'tags', 'type' => 'select', 'multiple' => 1, 'choices' => ['x' => 'X']],
            ['key' => 'k2', 'name' => 'terms', 'type' => 'taxonomy', 'taxonomy' => 'category', 'field_type' => 'multi_select', 'return_format' => 'object'],
            ['key' => 'k3', 'name' => 'when', 'type' => 'date_picker', 'display_format' => 'd/m/Y'],
            ['key' => 'k4', 'name' => 'rel', 'type' => 'relationship', 'post_type' => ['page', 'post'], 'max' => '3'],
            ['key' => 'k5', 'name' => 'pic', 'type' => 'image'],
            ['key' => 'k6', 'name' => 'who', 'type' => 'user', 'role' => ['editor'], 'multiple' => 1],
        ])['group']['fields'];

        $this->assertSame('checkbox', $f[0]['type'], 'multiple select');
        $this->assertSame(['taxonomy' => 'category', 'multiple' => true, 'appearance' => 'select', 'returnFormat' => 'object'],
            array_intersect_key($f[1], array_flip(['taxonomy', 'multiple', 'appearance', 'returnFormat'])));
        $this->assertSame('d/m/Y', $f[2]['returnFormat'], 'ACF default return format');
        $this->assertSame(['page', 3], [$f[3]['relatedPostType'], $f[3]['maxItems']]);
        $this->assertSame('array', $f[4]['returnFormat'], 'ACF image default');
        $this->assertSame(['editor'], $f[5]['roles']);
    }

    public function test_nested_fields_and_layouts(): void
    {
        $f = $this->convert([
            ['key' => 'r', 'name' => 'team', 'type' => 'repeater', 'sub_fields' => [['key' => 'r1', 'name' => 'name', 'type' => 'text']]],
            ['key' => 'fc', 'name' => 'sections', 'type' => 'flexible_content', 'layouts' => [
                ['key' => 'l1', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => [['key' => 'h1', 'name' => 'title', 'type' => 'text']]],
            ]],
        ])['group']['fields'];

        $this->assertSame('name', $f[0]['fields'][0]['key']);
        $this->assertSame(['key' => 'hero', 'label' => 'Hero', 'fields' => [['key' => 'title', 'type' => 'text', 'label' => '']]], $f[1]['layouts'][0]);
    }

    public function test_conditional_logic(): void
    {
        $r = $this->convert([
            ['key' => 'field_a', 'name' => 'show', 'type' => 'true_false'],
            ['key' => 'field_b', 'name' => 'note', 'type' => 'text', 'conditional_logic' => [[['field' => 'field_a', 'operator' => '==', 'value' => '1']]]],
            ['key' => 'field_c', 'name' => 'other', 'type' => 'text', 'conditional_logic' => [[['field' => 'field_a', 'operator' => '!=empty']]]],
            ['key' => 'field_d', 'name' => 'complex', 'type' => 'text', 'conditional_logic' => [
                [['field' => 'field_a', 'operator' => '==', 'value' => '1']], [['field' => 'field_a', 'operator' => '==', 'value' => '0']],
            ]],
        ]);
        $f = $r['group']['fields'];

        $this->assertSame(['field' => 'show', 'operator' => '==', 'value' => '1'], $f[1]['visibleWhen']);
        $this->assertSame('not_empty', $f[2]['visibleWhen']['operator']);
        $this->assertArrayNotHasKey('visibleWhen', $f[3]);
        $this->assertArrayNotHasKey('_acfCondition', $f[3]);
        $this->assertCount(1, $r['warnings']);
    }

    public function test_location_shapes(): void
    {
        $and = $this->convert([], [[
            ['param' => 'post_type', 'operator' => '==', 'value' => 'page'],
            ['param' => 'page_template', 'operator' => '!=', 'value' => 'default'],
        ]])['group'];
        $this->assertCount(2, $and['location']);
        $this->assertSame([], $and['locationAny']);

        $or = $this->convert([], [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'page']],
            [['param' => 'user_form', 'operator' => '==', 'value' => 'all']],
        ])['group'];
        $this->assertSame([], $or['location']);
        $this->assertSame(['key' => 'context', 'operator' => '==', 'value' => 'user_profile'], $or['locationAny'][1]);

        $r = $this->convert([], [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'page'], ['param' => 'page_type', 'operator' => '==', 'value' => 'front_page']],
            [['param' => 'post_type', 'operator' => '==', 'value' => 'post'], ['param' => 'block', 'operator' => '==', 'value' => 'x']],
        ]);
        $this->assertCount(2, $r['group']['location']);
        $this->assertCount(2, $r['warnings'], 'unsupported "block" rule + only the first group');
    }

    public function test_converted_groups_pass_validation(): void
    {
        $r = $this->convert([
            ['key' => 'field_a', 'name' => 'kind', 'type' => 'radio', 'choices' => ['a' => 'A']],
            ['key' => 'field_b', 'name' => 'note', 'type' => 'textarea', 'conditional_logic' => [[['field' => 'field_a', 'operator' => '==', 'value' => 'a']]]],
            ['key' => 'field_c', 'name' => 'box', 'type' => 'group', 'sub_fields' => [['key' => 'x', 'name' => 'city', 'type' => 'text']]],
            ['key' => 'field_d', 'name' => 'day', 'type' => 'date_time_picker', 'return_format' => 'Y-m-d H:i'],
        ]);

        [, $errors] = JsonGroup::normalize($r['group']);
        $this->assertSame([], $errors);
    }
}
