<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\Inspector;

use CtrlField\Admin\Inspector\SchemaInspectorRenderer;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class SchemaInspectorTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_renders_empty_state_when_no_groups(): void
    {
        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('No field groups registered', $html);
    }

    public function test_renders_group_key_and_title(): void
    {
        Field::group('portfolio_details')
            ->title('Portfolio Details')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('client')->setIndex(true)])
            ->register();

        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('portfolio_details', $html);
        $this->assertStringContainsString('Portfolio Details', $html);
    }

    public function test_renders_field_details(): void
    {
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('client_name')->label('Client')->setIndex(true)->adminColumn(true),
            ])
            ->register();

        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('client_name', $html);
        $this->assertStringContainsString('Client', $html);
    }

    public function test_renders_context_label(): void
    {
        Field::group('user_fields')
            ->where('context', '==', 'user_profile')
            ->fields([Field::text('bio')])
            ->register();

        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('context == user_profile', $html);
    }

    public function test_renders_multiple_groups(): void
    {
        Field::group('group_a')
            ->where('post_type', '==', 'post')
            ->fields([Field::text('a')])
            ->register();

        Field::group('group_b')
            ->where('post_type', '==', 'page')
            ->fields([Field::text('b')])
            ->register();

        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('group_a', $html);
        $this->assertStringContainsString('group_b', $html);
    }

    public function test_shows_index_checkmark_in_field_detail(): void
    {
        Field::group('g')
            ->where('post_type', '==', 'post')
            ->fields([
                Field::text('indexed_field')->setIndex(true),
                Field::text('plain_field'),
            ])
            ->register();

        ob_start();
        (new SchemaInspectorRenderer())->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('indexed_field', $html);
        $this->assertStringContainsString('plain_field', $html);
    }
}
