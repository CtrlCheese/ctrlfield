<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Link;

use CtrlField\Data\LinkResolver;
use CtrlField\Fields\Field;
use CtrlField\Fields\Sanitizers\LinkSanitizer;
use PHPUnit\Framework\TestCase;

/** ->multiple() / ->max(), ->defaultStyles(), ->icons(), typed paths, ctrlfield_button(). */
final class LinkButtonsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_url_to_postid'], $GLOBALS['_wp_post_types']);
    }

    public function test_multiple_defaults_to_two_links(): void
    {
        $field = Field::link('links')->multiple();

        $this->assertTrue($field->isMultiple());
        $this->assertSame(2, $field->getMax());
        $this->assertSame(3, Field::link('links')->multiple()->max(3)->getMax());
        $this->assertSame(1, Field::link('links')->multiple()->max(0)->getMax());
    }

    public function test_a_multiple_value_is_a_list_capped_at_max_without_empty_links(): void
    {
        $links = LinkSanitizer::forField(Field::link('links')->multiple())->sanitizeValue([
            ['url' => 'https://other.test/a', 'title' => 'A'],
            ['url' => '', 'title' => 'empty'],
            ['url' => 'https://other.test/b', 'title' => 'B'],
            ['url' => 'https://other.test/c', 'title' => 'C'],
        ]);

        $this->assertSame(['A', 'B'], array_column($links, 'title'));
    }

    public function test_a_single_link_becomes_a_list_of_one(): void
    {
        $links = LinkSanitizer::forField(Field::link('links')->multiple())->sanitizeValue(['url' => 'https://other.test/a', 'title' => 'A']);

        $this->assertCount(1, $links);
        $this->assertSame('A', $links[0]['title']);
    }

    public function test_default_styles_keep_only_offered_styles(): void
    {
        $field = Field::link('cta')
            ->styles(['primary' => 'Primary', 'outline' => 'Outline'])
            ->defaultStyles(['primary', 'ghost', 'outline']);

        $this->assertSame(['primary', 'outline'], $field->getDefaultStyles());
    }

    public function test_icons_are_kept_only_when_the_field_offers_them(): void
    {
        $value = ['url' => 'https://other.test/', 'title' => 'Go', 'icon' => 'lucide:arrow-right', 'icon_position' => 'right'];

        $with    = LinkSanitizer::forField(Field::link('cta')->icons())->sanitizeValue($value);
        $without = LinkSanitizer::forField(Field::link('cta'))->sanitizeValue($value);
        $bad     = LinkSanitizer::forField(Field::link('cta')->icons())->sanitizeValue(['icon' => '<svg>'] + $value);

        $this->assertSame('lucide:arrow-right', $with['icon']);
        $this->assertSame('right', $with['icon_position']);
        $this->assertArrayNotHasKey('icon', $without);
        $this->assertArrayNotHasKey('icon', $bad);
    }

    public function test_a_typed_path_of_a_post_becomes_a_post_link(): void
    {
        $GLOBALS['_wp_url_to_postid'] = ['https://example.test/services/' => 7];
        $GLOBALS['_wp_post_types']    = [7 => 'page'];

        $link = (new LinkSanitizer())->sanitize(['url' => '/services/#price', 'title' => 'Prices', 'type' => 'internal']);

        $this->assertSame('post', $link['type']);
        $this->assertSame(7, $link['id']);
        $this->assertSame('page', $link['object']);
        $this->assertSame('/services/#price', $link['url']); // the fragment stays
    }

    public function test_typed_urls_are_classified(): void
    {
        $s = new LinkSanitizer();

        $this->assertSame('internal', $s->sanitize(['url' => '/not-a-post/', 'type' => 'internal'])['type']);
        $this->assertSame('anchor', $s->sanitize(['url' => '#contact', 'type' => 'external'])['type']);
        $this->assertSame('external', $s->sanitize(['url' => 'https://other.test/x', 'type' => 'external'])['type']);
        $this->assertSame('internal', $s->sanitize(['url' => 'https://example.test/x', 'type' => 'external'])['type']);
    }

    public function test_button_prints_style_class_and_icon_side(): void
    {
        $html = LinkResolver::button([
            'url' => 'https://other.test/', 'title' => 'Go', 'target' => '_blank',
            'style' => 'primary', 'icon' => 'lucide:arrow-right', 'icon_position' => 'right',
        ]);

        $this->assertStringStartsWith('<a href="https://other.test/" target="_blank" rel="noopener noreferrer" class="btn btn--primary">', $html);
        $this->assertLessThan(strpos($html, '<svg'), strpos($html, '<span>Go</span>'));
    }

    public function test_button_prints_a_list_and_skips_empty_links(): void
    {
        $html = LinkResolver::button(
            [['url' => '/a', 'title' => 'A'], ['url' => '', 'title' => 'none'], ['url' => '/b', 'title' => 'B', 'style' => 'outline']],
            ['class' => 'button', 'prefix' => 'button--']
        );

        $this->assertSame(2, substr_count($html, '<a '));
        $this->assertStringContainsString('class="button button--outline"', $html);
        $this->assertStringNotContainsString('none', $html);
    }

    public function test_icon_position_defaults_to_the_field_or_the_site(): void
    {
        $value = ['url' => 'https://other.test/', 'icon' => 'lucide:arrow-right'];

        $this->assertSame('left', LinkSanitizer::forField(Field::link('a')->icons())->sanitizeValue($value)['icon_position']);
        $this->assertSame('right', LinkSanitizer::forField(Field::link('a')->icons()->defaultIconPosition('right'))->sanitizeValue($value)['icon_position']);

        $GLOBALS['_wp_test_filters']['ctrlfield/link/icon_position'] = static fn (): string => 'right';
        try {
            $this->assertSame('right', Field::link('a')->getDefaultIconPosition());
            // A side chosen in the dialog wins.
            $this->assertSame('left', LinkSanitizer::forField(Field::link('a')->icons())->sanitizeValue($value + ['icon_position' => 'left'])['icon_position']);
        } finally {
            unset($GLOBALS['_wp_test_filters']);
        }
    }
}
