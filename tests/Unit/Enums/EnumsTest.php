<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Enums;

use CtrlField\Enums\AdminTab;
use CtrlField\Enums\FieldType;
use CtrlField\Enums\SanitizationMode;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_admin_tab_cases(): void
    {
        $this->assertSame('content', AdminTab::CONTENT->value);
        $this->assertSame('sidebar', AdminTab::SIDEBAR->value);
        $this->assertSame('settings', AdminTab::SETTINGS->value);
    }

    public function test_field_type_covers_all_types(): void
    {
        $expected = [
            // v1
            'text', 'textarea', 'number', 'email', 'url',
            'select', 'checkbox', 'radio',
            'image', 'file',
            'group', 'repeater', 'wysiwyg',
            // v2 (Cycle A-1)
            'date', 'time', 'datetime', 'color', 'link', 'range', 'oembed',
            // Pro (Cycle B-1)
            'flexible_content',
            // Pro (Cycle B-2)
            'post_object', 'taxonomy_term', 'relationship',
            // Pro (Cycle B-5)
            'gallery', 'map',
            // Pro (Cycle B-6)
            'clone',
            // Core (Cycle A-10)
            'computed',
            // Core CYCLES4 — new field types
            'true_false', 'tab', 'accordion', 'accordion_end',
            'message', 'separator', 'button_group', 'user', 'icon', 'code',
        ];

        $actual = array_map(fn(FieldType $t) => $t->value, FieldType::cases());

        $this->assertSame($expected, $actual);
    }

    public function test_sanitization_mode_cases(): void
    {
        $this->assertSame('strict', SanitizationMode::STRICT->value);
        $this->assertSame('html', SanitizationMode::HTML->value);
        $this->assertSame('raw', SanitizationMode::RAW->value);
    }

    public function test_field_type_from_string(): void
    {
        $this->assertSame(FieldType::REPEATER, FieldType::from('repeater'));
        $this->assertSame(FieldType::WYSIWYG, FieldType::from('wysiwyg'));
    }
}
