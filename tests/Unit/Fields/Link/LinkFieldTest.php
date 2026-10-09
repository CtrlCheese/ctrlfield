<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Link;

use CtrlField\Data\LinkResolver;
use CtrlField\Fields\Field;
use CtrlField\Fields\Sanitizers\LinkSanitizer;
use CtrlField\Schema\JsonGroup;
use PHPUnit\Framework\TestCase;

/** Link field: dialog data (type / id / object / style), resolution on read, JSON settings. */
final class LinkFieldTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_posts']);
    }

    public function test_a_picked_post_keeps_its_id_and_type(): void
    {
        $link = (new LinkSanitizer())->sanitize([
            'url' => 'https://example.test/about/', 'title' => 'About', 'target' => '_self',
            'type' => 'post', 'id' => '12', 'object' => 'page',
        ]);

        $this->assertSame(
            ['url' => 'https://example.test/about/', 'title' => 'About', 'target' => '_self', 'type' => 'post', 'id' => 12, 'object' => 'page'],
            $link,
        );
    }

    public function test_old_links_are_stored_unchanged(): void
    {
        $this->assertSame(
            ['url' => 'https://example.test', 'title' => 'Home', 'target' => '_blank'],
            (new LinkSanitizer())->sanitize(['url' => 'https://example.test', 'title' => 'Home', 'target' => '_blank']),
        );
    }

    public function test_only_known_types_and_allowed_styles_are_kept(): void
    {
        $link = (new LinkSanitizer(['primary', 'outline']))->sanitize([
            'url' => 'https://x.test', 'type' => 'evil', 'id' => 5, 'style' => 'primary',
        ]);
        $this->assertArrayNotHasKey('type', $link);
        $this->assertArrayNotHasKey('id', $link, 'an id only means something for posts and terms');
        $this->assertSame('primary', $link['style']);

        $this->assertArrayNotHasKey('style', (new LinkSanitizer(['primary']))->sanitize(['url' => '/', 'style' => 'huge']));
        $this->assertArrayNotHasKey('style', (new LinkSanitizer())->sanitize(['url' => '/', 'style' => 'primary']), 'no styles configured');
    }

    public function test_anchor_urls_are_kept_as_typed(): void
    {
        $link = (new LinkSanitizer())->sanitize(['url' => '#contact', 'type' => 'anchor']);
        $this->assertSame('#contact', $link['url']);
        $this->assertSame('anchor', $link['type']);
    }

    public function test_a_post_link_follows_the_current_permalink(): void
    {
        $GLOBALS['_wp_posts'] = [12 => (object) ['ID' => 12, 'post_status' => 'publish']];

        $resolved = LinkResolver::resolve(['url' => 'https://old.test/about/', 'title' => 'About', 'target' => '_self', 'type' => 'post', 'id' => 12]);

        $this->assertSame('https://example.test/?p=12', $resolved['url']);
    }

    public function test_a_fragment_typed_after_the_address_is_kept(): void
    {
        $GLOBALS['_wp_posts'] = [12 => (object) ['ID' => 12, 'post_status' => 'publish']];

        $resolved = LinkResolver::resolve(['url' => 'https://old.test/about/#team', 'type' => 'post', 'id' => 12]);

        $this->assertSame('https://example.test/?p=12#team', $resolved['url']);
    }

    public function test_a_deleted_or_unpublished_post_keeps_the_stored_url(): void
    {
        $GLOBALS['_wp_posts'] = [13 => (object) ['ID' => 13, 'post_status' => 'draft']];
        $link = ['url' => 'https://old.test/x/', 'type' => 'post', 'id' => 13];

        $this->assertSame($link, LinkResolver::resolve($link));
        $this->assertSame(['url' => 'https://old.test/y/', 'type' => 'post', 'id' => 99], LinkResolver::resolve(['url' => 'https://old.test/y/', 'type' => 'post', 'id' => 99]));
    }

    public function test_external_links_and_other_values_are_untouched(): void
    {
        $this->assertSame(['url' => 'https://x.test', 'type' => 'external'], LinkResolver::resolve(['url' => 'https://x.test', 'type' => 'external']));
        $this->assertNull(LinkResolver::resolve(null));
        $this->assertSame('https://x.test', LinkResolver::resolve('https://x.test'));
    }

    public function test_attributes_for_an_anchor_tag(): void
    {
        $this->assertSame('href="https://x.test" target="_blank" rel="noopener noreferrer"', LinkResolver::attributes(['url' => 'https://x.test', 'target' => '_blank']));
        $this->assertSame('href="#contact"', LinkResolver::attributes(['url' => '#contact', 'target' => '_self']));
        $this->assertSame('', LinkResolver::attributes(['url' => '']));
    }

    public function test_json_settings_reach_the_field(): void
    {
        [$data, $errors] = JsonGroup::normalize([
            'key' => 'cta_group', 'title' => 'CTA',
            'location' => [['key' => 'post_type', 'operator' => '==', 'value' => 'page']],
            'fields' => [[
                'key' => 'cta', 'type' => 'link',
                'postType' => ['page'], 'taxonomies' => ['category'], 'noAnchors' => true,
                'styles' => [['primary', 'Primary'], ['outline', 'Outline']],
            ]],
        ]);
        $this->assertSame([], $errors);
        $group = JsonGroup::build($data);

        /** @var \CtrlField\Fields\Types\LinkField $link */
        $link = $group->getFields()[0];
        $this->assertSame(['page'], $link->getPostTypes());
        $this->assertSame(['category'], $link->getTaxonomies());
        $this->assertFalse($link->getAnchors());
        $this->assertSame(['primary' => 'Primary', 'outline' => 'Outline'], $link->getStyles());
    }

    public function test_style_values_are_slugs(): void
    {
        $this->assertSame(['primary' => 'P'], Field::link('x')->styles(['Primary' => 'P', 'bad value!' => 'B'])->getStyles());
    }
}
