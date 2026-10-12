<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Data;

use CtrlField\Data\IconLibrary;
use PHPUnit\Framework\TestCase;

final class IconLibraryTest extends TestCase
{
    public function test_parses_both_libraries(): void
    {
        $this->assertSame(['lucide', 'arrow-right'], IconLibrary::parse('lucide:arrow-right'));
        $this->assertSame(['dashicons', 'admin-site'], IconLibrary::parse('dashicons-admin-site'));
        $this->assertSame(['', ''], IconLibrary::parse('lucide:<script>'));
        $this->assertSame(['', ''], IconLibrary::parse(['lucide:x']));
    }

    public function test_lucide_prints_inline_svg(): void
    {
        $html = IconLibrary::html('lucide:arrow-right', ['class' => 'size-5', 'width' => 20]);

        $this->assertStringStartsWith('<svg', $html);
        $this->assertStringContainsString('class="lucide lucide-arrow-right size-5"', $html);
        $this->assertStringContainsString('width="20"', $html);
        $this->assertStringContainsString('<path d="M5 12h14"/>', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_label_makes_the_icon_an_image(): void
    {
        $html = IconLibrary::html('lucide:phone', ['aria-label' => 'Phone']);

        $this->assertStringContainsString('role="img" aria-label="Phone"', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
    }

    public function test_dashicons_print_a_span(): void
    {
        $this->assertSame(
            '<span class="dashicons dashicons-admin-site" aria-hidden="true"></span>',
            IconLibrary::html('dashicons-admin-site')
        );
    }

    public function test_unknown_icons_print_nothing(): void
    {
        $this->assertSame('', IconLibrary::html('lucide:does-not-exist'));
        $this->assertSame('', IconLibrary::html(''));
        $this->assertSame('', IconLibrary::html('fa fa-home'));
    }
}
