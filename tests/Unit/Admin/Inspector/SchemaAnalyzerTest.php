<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\Inspector;

use CtrlField\Admin\Inspector\SchemaAnalyzer;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

final class SchemaAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    public function test_tree_includes_sub_fields_and_counts_value_fields(): void
    {
        $tree = SchemaAnalyzer::tree([
            Field::tab('t')->label('Main'),
            Field::text('title')->label('Title')->required()->width(50),
            Field::object('address')->label('Address')->fields([Field::text('street'), Field::text('city')]),
        ]);

        $this->assertSame(['t', 'title', 'address'], array_column($tree, 'key'));
        $this->assertSame(['street', 'city'], array_column($tree[2]['children'], 'key'));
        $this->assertSame('address.street', $tree[2]['children'][0]['path']);
        $this->assertTrue($tree[1]['required']);
        $this->assertSame(50, $tree[1]['width']);
        $this->assertSame(4, SchemaAnalyzer::count($tree)); // the tab does not count
    }

    public function test_conditions_read_as_words(): void
    {
        $text = SchemaAnalyzer::conditionText(Field::text('sub')->visibleWhen('layout', '==', 'b'));

        $this->assertSame('Shown when layout = b', $text);
        $this->assertSame('', SchemaAnalyzer::conditionText(Field::text('x')));
    }

    public function test_where_place_and_post_types(): void
    {
        $group = FieldGroup::make('g')->title('G')
            ->whereAny([['post_type', '==', 'project'], ['post_type', '==', 'page']])
            ->fields([Field::text('a')]);

        $this->assertSame(['project', 'page'], SchemaAnalyzer::postTypes($group));
        $this->assertSame('post', SchemaAnalyzer::place($group));
        $this->assertSame('Post type is Project or Page', SchemaAnalyzer::where($group, 'ucfirst'));
        $this->assertSame('Post type is Post · Template is Default', SchemaAnalyzer::where(
            FieldGroup::make('h')->where('post_type', '==', 'post')->where('page_template', '==', 'default')->fields([Field::text('c')]),
            'ucfirst'
        ));

        $options = FieldGroup::make('o')->where('options_page', '==', 'site')->fields([Field::text('b')]);
        $this->assertSame('options', SchemaAnalyzer::place($options));
    }

    public function test_source(): void
    {
        $code = FieldGroup::make('code_group')->where('post_type', '==', 'post')->fields([Field::text('a')]);
        $analyzer = new SchemaAnalyzer([], ['json_group'], ['acf_group']);

        $this->assertSame('code', $analyzer->source('code_group', $code));
        $this->assertSame('json', $analyzer->source('json_group', $code));
        $this->assertSame('acf', $analyzer->source('acf_group', $code));
    }

    public function test_issues(): void
    {
        $groups = [
            'one' => FieldGroup::make('one')->where('post_type', '==', 'page')->fields([
                Field::text('title')->label('Title'),
                Field::text('subtitle')->label('Subtitle')->visibleWhen('missing_field', '==', '1'),
                Field::object('rows')->label('Rows')->fields([
                    Field::text('cell')->label('Cell')->visibleWhen('title', '!=', ''), // looks up: fine
                ]),
                Field::text('nolabel'),
            ]),
            'two' => FieldGroup::make('two')->where('post_type', '==', 'page')->fields([
                Field::text('title')->label('Other title'),
                Field::text('tpl')->label('Tpl')->visibleWhen('post_type', '==', 'page'), // context key: fine
            ]),
        ];

        $codes = array_map(
            static fn (array $i): string => $i['code'] . ':' . $i['field'],
            (new SchemaAnalyzer($groups))->issues()
        );
        sort($codes);

        $this->assertSame(['condition_target:subtitle', 'duplicate_key:title', 'no_label:nolabel'], $codes);
    }
}
