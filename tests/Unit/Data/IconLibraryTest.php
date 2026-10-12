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

    public function test_a_theme_can_add_an_icon_set(): void
    {
        $file = sys_get_temp_dir() . '/ctrlf_brands_' . uniqid() . '.json';
        file_put_contents($file, json_encode(['icons' => [
            'facebook' => '<path d="M1 1"/>',
            'x'        => ['body' => '<path d="M2 2"/>', 'viewBox' => '0 0 512 512'],
        ]]));
        $GLOBALS['_wp_test_filters']['ctrlfield/icon_sets'] = static fn (array $sets): array => $sets + [
            'brand'     => ['label' => 'Brands', 'file' => $file, 'url' => 'https://t.test/brands.json'],
            'dashicons' => ['label' => 'not allowed'],
        ];
        IconLibrary::reset();

        try {
            $this->assertSame(['lucide', 'brand', 'dashicons'], IconLibrary::libraries());
            $this->assertSame(['brand', 'facebook'], IconLibrary::parse('brand:facebook'));
            $this->assertTrue(IconLibrary::isValid('brand:x'));

            $html = IconLibrary::html('brand:x');
            $this->assertStringContainsString('viewBox="0 0 512 512"', $html);
            $this->assertStringContainsString('fill="currentColor"', $html);
            $this->assertStringContainsString('class="brand brand-x"', $html);
            $this->assertSame('', IconLibrary::html('brand:missing'));
        } finally {
            unset($GLOBALS['_wp_test_filters']);
            IconLibrary::reset();
            @unlink($file);
        }
    }

    public function test_unknown_sets_are_not_icons(): void
    {
        IconLibrary::reset();
        $this->assertSame(['', ''], IconLibrary::parse('mdi:star'));
    }
}
