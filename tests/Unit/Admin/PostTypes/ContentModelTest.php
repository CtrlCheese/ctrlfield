<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\PostTypes;

use CtrlField\Admin\PostTypes\ContentModel;
use PHPUnit\Framework\TestCase;

final class ContentModelTest extends TestCase
{
    /** @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'type'   => ['plural' => 'Team members', 'singular' => 'Team member', 'slug' => 'team-member', 'icon' => 'dashicons-groups', 'public' => '1', 'has_archive' => '1', 'supports' => ['title', 'thumbnail']],
            'link'   => ['category'],
            'new'    => [
                ['plural' => 'Departments', 'singular' => 'Department', 'slug' => 'department', 'hierarchical' => '1'],
                ['plural' => '', 'singular' => '', 'slug' => ''], // empty row left in the form
            ],
            'fields' => ['create' => '1', 'title' => ''],
        ], $overrides);
    }

    public function test_builds_the_whole_model(): void
    {
        [$model, $errors] = ContentModel::fromInput($this->input());

        self::assertSame([], $errors);
        self::assertSame('team-member', $model?->postType->slug);
        self::assertSame(['category'], $model?->postType->taxonomies);
        self::assertTrue($model?->postType->showInRest);
        self::assertCount(1, $model?->taxonomies ?? []);
        self::assertSame(['team-member'], $model?->taxonomies[0]->postTypes);
        self::assertSame('team_member_fields', $model?->group['key']);
        self::assertSame('Team member', $model?->group['title']);
        self::assertSame('team-member', $model?->group['location'][0]['value']);
    }

    public function test_nothing_is_built_when_a_part_is_wrong(): void
    {
        [$model, $errors] = ContentModel::fromInput($this->input([
            'type' => ['slug' => 'year'],
            'new'  => [['slug' => 'category']],
        ]));

        self::assertNull($model);
        self::assertArrayHasKey('type.slug', $errors);
        self::assertArrayHasKey('tax.0.slug', $errors);
    }

    public function test_keys_in_use(): void
    {
        [, $errors] = ContentModel::fromInput($this->input(), [
            'post_types' => ['team-member'], 'taxonomies' => ['department'], 'groups' => ['team_member_fields'],
        ]);

        self::assertArrayHasKey('type.slug', $errors);
        self::assertArrayHasKey('tax.0.slug', $errors);
        self::assertArrayHasKey('group', $errors);
    }

    public function test_without_field_group_or_taxonomies(): void
    {
        [$model] = ContentModel::fromInput(['type' => ['plural' => 'Logs', 'singular' => 'Log', 'slug' => 'log'], 'fields' => []]);

        self::assertNull($model?->group);
        self::assertSame([], $model?->taxonomies);
        self::assertFalse($model?->postType->public);
    }
}
