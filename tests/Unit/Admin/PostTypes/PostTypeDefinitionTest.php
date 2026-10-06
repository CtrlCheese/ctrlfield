<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\PostTypes;

use CtrlField\Admin\PostTypes\PostTypeCodeExporter;
use CtrlField\Admin\PostTypes\PostTypeDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostTypeDefinitionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'slug'          => 'project',
            'singular'      => 'Project',
            'plural'        => 'Projects',
            'icon'          => 'dashicons-portfolio',
            'supports'      => ['title', 'editor', 'thumbnail'],
            'public'        => '1',
            'has_archive'   => '1',
            'show_in_rest'  => '1',
            'menu_position' => '25',
        ], $overrides);
    }

    public function test_valid_input_builds_definition(): void
    {
        [$def, $errors] = PostTypeDefinition::fromInput($this->validInput());

        self::assertSame([], $errors);
        self::assertNotNull($def);
        self::assertSame('project', $def->slug);
        self::assertSame(['title', 'editor', 'thumbnail'], $def->supports);
        self::assertTrue($def->public);
        self::assertFalse($def->hierarchical, 'unchecked checkbox is absent from POST → false');
    }

    /** @return array<string, array{0: string}> */
    public static function invalidSlugs(): array
    {
        return [
            'empty'              => [''],
            'starts with digit'  => ['1project'],
            'uppercase is lowered but spaces are not allowed' => ['my project'],
            'too long (21 chars)' => ['abcdefghijklmnopqrstu'],
            'reserved: post'     => ['post'],
            'reserved: page'     => ['page'],
            'reserved: wp_block' => ['wp_block'],
        ];
    }

    #[DataProvider('invalidSlugs')]
    public function test_invalid_slug_is_rejected(string $slug): void
    {
        [$def, $errors] = PostTypeDefinition::fromInput($this->validInput(['slug' => $slug]));

        self::assertNull($def);
        self::assertArrayHasKey('slug', $errors);
    }

    public function test_slug_is_lowercased(): void
    {
        [$def] = PostTypeDefinition::fromInput($this->validInput(['slug' => 'Event']));

        self::assertSame('event', $def?->slug);
    }

    public function test_names_are_required_and_stripped_of_html(): void
    {
        [$def, $errors] = PostTypeDefinition::fromInput($this->validInput(['singular' => '', 'plural' => '<b>  </b>']));
        self::assertNull($def);
        self::assertArrayHasKey('singular', $errors);
        self::assertArrayHasKey('plural', $errors);

        [$def] = PostTypeDefinition::fromInput($this->validInput(['plural' => '<script>x</script>Events  &  Talks']));
        self::assertSame('xEvents & Talks', $def?->plural);
    }

    public function test_unknown_supports_and_bad_icon(): void
    {
        [$def, $errors] = PostTypeDefinition::fromInput($this->validInput(['icon' => 'fa-star']));
        self::assertNull($def);
        self::assertArrayHasKey('icon', $errors);

        [$def] = PostTypeDefinition::fromInput($this->validInput(['supports' => ['title', 'evil', 'thumbnail']]));
        self::assertSame(['title', 'thumbnail'], $def?->supports);
    }

    public function test_menu_position_is_clamped_and_rewrite_slug_trimmed(): void
    {
        [$def] = PostTypeDefinition::fromInput($this->validInput(['menu_position' => '999', 'rewrite_slug' => '/work/']));

        self::assertSame(100, $def?->menuPosition);
        self::assertSame('work', $def?->rewriteSlug);
    }

    public function test_array_round_trip(): void
    {
        [$def] = PostTypeDefinition::fromInput($this->validInput(['hierarchical' => 'on', 'description' => 'Client work']));

        self::assertEquals($def, PostTypeDefinition::fromArray($def?->toArray() ?? []));
    }

    public function test_exporter_produces_builder_code(): void
    {
        [$def] = PostTypeDefinition::fromInput($this->validInput(['plural' => "Client's Projects", 'rewrite_slug' => 'work']));
        self::assertNotNull($def);

        $code = (new PostTypeCodeExporter())->export($def);

        self::assertStringContainsString("CPT::make('project')", $code);
        self::assertStringContainsString("->label('Project', 'Client\\'s Projects')", $code);
        self::assertStringContainsString("->supports(['title', 'editor', 'thumbnail'])", $code);
        self::assertStringContainsString("->rewriteSlug('work')", $code);
        self::assertStringEndsWith("->register();\n", $code);
        // The generated file must be valid PHP.
        self::assertNotFalse(@token_get_all($code, TOKEN_PARSE));
    }
}
