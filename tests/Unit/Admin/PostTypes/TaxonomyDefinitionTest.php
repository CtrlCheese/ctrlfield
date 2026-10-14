<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\PostTypes;

use CtrlField\Admin\PostTypes\PostTypeCodeExporter;
use CtrlField\Admin\PostTypes\TaxonomyDefinition;
use PHPUnit\Framework\TestCase;

final class TaxonomyDefinitionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'service', 'singular' => 'Service', 'plural' => 'Services',
            'post_types' => ['project', 'event'], 'hierarchical' => '1', 'public' => '1', 'show_in_rest' => '1',
        ], $overrides);
    }

    public function test_valid_input(): void
    {
        [$def, $errors] = TaxonomyDefinition::fromInput($this->input(['post_types' => ['project', 'Not valid', 'event'], 'rewrite_slug' => '/services/']));

        self::assertSame([], $errors);
        self::assertSame(['project', 'event'], $def?->postTypes);
        self::assertTrue($def?->hierarchical);
        self::assertFalse($def?->showAdminColumn, 'unchecked → false');
        self::assertSame('services', $def?->rewriteSlug);
    }

    public function test_keys(): void
    {
        foreach (['category', 'post_tag', 'year', 'term', '1x', str_repeat('a', 33)] as $slug) {
            [, $errors] = TaxonomyDefinition::fromInput($this->input(['slug' => $slug]));
            self::assertArrayHasKey('slug', $errors, $slug);
        }
        [$def] = TaxonomyDefinition::fromInput($this->input(['slug' => str_repeat('a', 32)]));
        self::assertNotNull($def, '32 characters are allowed');
    }

    public function test_round_trip_and_export(): void
    {
        [$def] = TaxonomyDefinition::fromInput($this->input(['hierarchical' => '', 'description' => "Client's services"]));
        self::assertEquals($def, TaxonomyDefinition::fromArray($def?->toArray() ?? []));

        $code = (new PostTypeCodeExporter())->exportTaxonomy($def);
        self::assertStringContainsString("Taxonomy::make('service')", $code);
        self::assertStringContainsString("->attachTo(['project', 'event'])", $code);
        self::assertStringContainsString('->hierarchical(false)', $code);
        self::assertStringContainsString("->description('Client\\'s services')", $code);
        self::assertNotFalse(@token_get_all($code, TOKEN_PARSE));
    }
}
