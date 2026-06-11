<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Builder;

use FieldForge\Builder\AdminContext;
use PHPUnit\Framework\TestCase;

class AdminContextTest extends TestCase
{
    public function test_defaults_are_null(): void
    {
        $ctx = new AdminContext();

        $this->assertNull($ctx->postType);
        $this->assertNull($ctx->optionsPage);
    }

    public function test_post_type_is_stored(): void
    {
        $ctx = new AdminContext(postType: 'portfolio');

        $this->assertSame('portfolio', $ctx->postType);
        $this->assertNull($ctx->optionsPage);
    }

    public function test_options_page_is_stored(): void
    {
        $ctx = new AdminContext(optionsPage: 'theme_settings');

        $this->assertNull($ctx->postType);
        $this->assertSame('theme_settings', $ctx->optionsPage);
    }

    public function test_both_properties_can_be_set(): void
    {
        $ctx = new AdminContext(postType: 'portfolio', optionsPage: 'theme_settings');

        $this->assertSame('portfolio', $ctx->postType);
        $this->assertSame('theme_settings', $ctx->optionsPage);
    }

    public function test_properties_are_readonly(): void
    {
        $ctx = new AdminContext(postType: 'portfolio');

        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line
        $ctx->postType = 'page';
    }
}
