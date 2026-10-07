<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Schema;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

final class JsonGroupTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
        Field::resetProFactories();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
        Field::resetProFactories();
    }

    /** @param list<array<string, mixed>> $fields */
    private function group(array $fields, array $extra = []): array
    {
        return $extra + [
            'key'      => 'demo',
            'title'    => 'Demo',
            'location' => [['key' => 'post_type', 'operator' => '==', 'value' => 'post']],
            'fields'   => $fields,
        ];
    }

    public function test_valid_group_is_normalized(): void
    {
        [$g, $errors] = JsonGroup::normalize($this->group([
            ['key' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle', 'required' => true, 'width' => '50'],
        ], ['position' => 'side']));

        $this->assertSame([], $errors);
        $this->assertSame('side', $g['position']);
        $this->assertTrue($g['active']);
        $this->assertSame(['key' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle', 'required' => true, 'width' => 50], $g['fields'][0]);
    }

    public function test_unknown_properties_and_settings_are_dropped(): void
    {
        [$g] = JsonGroup::normalize($this->group([
            ['key' => 'a', 'type' => 'text', 'options' => [['x', 'X']], 'php' => 'system("id")', 'taxonomy' => 'category'],
        ], ['evil' => '<?php']));

        $this->assertArrayNotHasKey('evil', $g);
        $this->assertSame(['key' => 'a', 'type' => 'text', 'label' => ''], $g['fields'][0]);
    }

    public function test_invalid_input_is_reported(): void
    {
        [, $errors] = JsonGroup::normalize([
            'key'    => '../etc/passwd',
            'title'  => '',
            'fields' => [
                ['key' => 'Bad Key', 'type' => 'text'],
                ['key' => 'x', 'type' => 'eval'],
                ['key' => 'dup', 'type' => 'text'],
                ['key' => 'dup', 'type' => 'text'],
            ],
        ]);

        $this->assertCount(5, $errors);
    }

    public function test_location_is_required_and_checked(): void
    {
        [, $none] = JsonGroup::normalize($this->group([], ['location' => []]));
        $this->assertNotEmpty($none);

        [, $unknown] = JsonGroup::normalize($this->group([], ['location' => [['key' => 'nope', 'operator' => '==', 'value' => 'x']]]));
        $this->assertStringContainsString('nope', $unknown[0]);
    }

    public function test_options_are_cleaned_and_keep_their_order(): void
    {
        [$g] = JsonGroup::normalize($this->group([
            ['key' => 's', 'type' => 'select', 'options' => [['b', 'Bee'], ['a', ''], ['b', 'again'], ['', 'empty'], 'junk']],
        ]));

        $this->assertSame([['b', 'Bee'], ['a', 'a']], $g['fields'][0]['options']);
    }

    public function test_condition_must_point_at_a_sibling(): void
    {
        [$g] = JsonGroup::normalize($this->group([
            ['key' => 'a', 'type' => 'text', 'visibleWhen' => ['field' => 'missing', 'operator' => '==', 'value' => '1']],
            ['key' => 'b', 'type' => 'text', 'visibleWhen' => ['field' => 'a', 'operator' => 'not_empty', 'value' => '']],
        ]));

        $this->assertArrayNotHasKey('visibleWhen', $g['fields'][0]);
        $this->assertSame('not_empty', $g['fields'][1]['visibleWhen']['operator']);
    }

    public function test_operator_must_suit_the_source_field(): void
    {
        [, $errors] = JsonGroup::normalize($this->group([
            ['key' => 's', 'type' => 'select', 'options' => [['a', 'A']]],
            ['key' => 't', 'type' => 'text', 'visibleWhen' => ['field' => 's', 'operator' => '>', 'value' => '1']],
        ]));

        $this->assertNotEmpty($errors);
    }

    public function test_pro_types_need_the_license(): void
    {
        [, $errors] = JsonGroup::normalize($this->group([
            ['key' => 'rows', 'type' => 'repeater', 'fields' => [['key' => 'cell', 'type' => 'text']]],
        ]));

        $this->assertStringContainsString('Pro', $errors[0]);
    }

    public function test_sub_fields_are_built(): void
    {
        [$g, $errors] = JsonGroup::normalize($this->group([
            ['key' => 'box', 'type' => 'group', 'label' => 'Box', 'fields' => [['key' => 'inner', 'type' => 'email']]],
        ]));
        $this->assertSame([], $errors);

        $built = JsonGroup::build($g);
        $this->assertSame('box', $built->getFields()[0]->getKey());
    }

    public function test_php_export_builds_the_same_group(): void
    {
        [$g, $errors] = JsonGroup::normalize($this->group([
            ['key' => 'title_x', 'type' => 'text', 'label' => "It's a \\ \"test\"", 'required' => true, 'adminColumn' => true],
            ['key' => 'kind', 'type' => 'radio', 'label' => 'Kind', 'options' => [['1', 'One'], ['two', "Two's"]]],
            ['key' => 'more', 'type' => 'textarea', 'visibleWhen' => ['field' => 'kind', 'operator' => '==', 'value' => 'two']],
            ['key' => 'score', 'type' => 'range', 'min' => 0, 'max' => '10', 'step' => '0.5', 'default' => '5'],
            ['key' => 'pages', 'type' => 'post_object', 'postType' => ['page', 'post'], 'multiple' => true, 'returnFormat' => 'object'],
            ['key' => 'cats', 'type' => 'taxonomy_term', 'taxonomy' => 'category', 'appearance' => 'checkbox'],
            ['key' => 'box', 'type' => 'group', 'fields' => [['key' => 'inner', 'type' => 'url', 'label' => 'Inner']]],
        ], ['position' => 'side', 'style' => 'seamless']));
        $this->assertSame([], $errors);

        $php  = JsonGroup::toPhp($g);
        $file = tempnam(sys_get_temp_dir(), 'cfjson');
        file_put_contents($file, str_replace(
            ['FieldGroup::make', "->register();"],
            ['return FieldGroup::make', ';'],
            $php
        ));
        /** @var FieldGroup $fromPhp */
        $fromPhp = require $file;
        unlink($file);

        $fromJson = JsonGroup::build($g);
        $sig = static fn (FieldGroup $fg): string => (string) json_encode([
            $fg->getTitle(), $fg->getPosition(), $fg->getStyle(), $fg->getAndConditions(),
            array_map(static fn ($f) => [$f->getKey(), $f->getType()->value, $f->getDefinition()], $fg->getFields()),
        ]);

        $this->assertSame($sig($fromJson), $sig($fromPhp));
        $this->assertStringContainsString("It\\'s", $php);
    }

    public function test_register_skips_keys_taken_by_code(): void
    {
        FieldGroup::make('demo')->where('post_type', '==', 'post')->fields([Field::text('x')])->register();
        [$g] = JsonGroup::normalize($this->group([['key' => 'y', 'type' => 'text']]));

        $this->assertFalse(JsonGroup::register($g));
        $this->assertSame('x', FieldRegistry::get('demo')->getFields()[0]->getKey());
    }
}
