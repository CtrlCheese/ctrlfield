<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Field;
use CtrlField\Fields\Renderers\PageLinkRenderer;
use CtrlField\Fields\Renderers\PasswordRenderer;
use CtrlField\Fields\Renderers\RendererRegistry;
use CtrlField\Fields\Types\MapField;
use CtrlField\Fields\Types\PageLinkField;
use CtrlField\Fields\Types\PasswordField;
use CtrlField\Fields\Types\PostObjectField;
use CtrlField\Fields\Types\RelationshipField;
use CtrlField\Fields\Types\TaxonomyField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Free/Pro split mirrors ACF: relational + map Free; repeater, gallery, flexible, clone Pro. */
final class AcfParityFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        RendererRegistry::reset();
        Field::resetProFactories();
    }

    public function test_relational_and_map_fields_are_free(): void
    {
        $this->assertInstanceOf(PostObjectField::class, Field::postObject('a'));
        $this->assertInstanceOf(TaxonomyField::class, Field::taxonomyTerm('b'));
        $this->assertInstanceOf(RelationshipField::class, Field::relationship('c'));
        $this->assertInstanceOf(MapField::class, Field::map('d'));
    }

    /** @return array<string, array{0: string}> */
    public static function proOnly(): array
    {
        return ['repeater' => ['repeater'], 'gallery' => ['gallery'], 'flexibleContent' => ['flexibleContent'], 'clone' => ['clone']];
    }

    #[DataProvider('proOnly')]
    public function test_pro_fields_need_a_license(string $factory): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/requires a CtrlField Pro license/');
        Field::$factory('x');
    }

    public function test_password_field_and_renderer(): void
    {
        $field = Field::password('api_secret');

        $this->assertInstanceOf(PasswordField::class, $field);
        $this->assertSame(FieldType::PASSWORD, $field->getType());
        $this->assertInstanceOf(PasswordRenderer::class, RendererRegistry::resolve(FieldType::PASSWORD));
        $html = RendererRegistry::resolve(FieldType::PASSWORD)->render($field, "adminState['api_secret']");
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
    }

    public function test_page_link_defaults_to_url_and_single_page(): void
    {
        $field = Field::pageLink('cta');

        $this->assertInstanceOf(PageLinkField::class, $field);
        $this->assertSame('url', $field->getReturnFormat());
        $this->assertSame(['page'], $field->getDefinition()['post_type']);
        $this->assertFalse($field->getDefinition()['multiple']);
        $this->assertInstanceOf(PageLinkRenderer::class, RendererRegistry::resolve(FieldType::PAGE_LINK));
    }

    public function test_page_link_sanitizes_ids(): void
    {
        $GLOBALS['_wp_post_types'] = [12 => 'page', 7 => 'page', 9 => 'page', 3 => 'post', 5 => 'page', 20 => 'project'];
        $single = Field::pageLink('a');
        $this->assertSame(12, $single->sanitizeForStorage('12'));
        $this->assertSame(0, $single->sanitizeForStorage('x'));
        $this->assertSame(7, $single->sanitizeForStorage(['7', '9']), 'single keeps the first');

        $multi = Field::pageLink('b')->multiple()->postType(['page', 'post']);
        $this->assertSame([3, 5], $multi->sanitizeForStorage(['3', '-1', 'abc', 5]));
        $this->assertSame(['page', 'post'], $multi->getPostTypes());
        $this->assertSame(0, $single->sanitizeForStorage(20), 'a project is not a page');
        unset($GLOBALS['_wp_post_types']);
    }

    public function test_multiple_taxonomy_keeps_a_single_id(): void
    {
        $GLOBALS['_wp_terms'] = [5 => (object) ['term_id' => 5], 8 => (object) ['term_id' => 8]];
        try {
            $multi = Field::taxonomyTerm('t')->taxonomy('category');
            $this->assertSame([5], $multi->sanitizeForStorage(5), 'a lone id used to be dropped');
            $this->assertSame([5, 8], $multi->sanitizeForStorage(['5', 8, 99]));
            $this->assertSame([], $multi->sanitizeForStorage(null));
            $this->assertSame(8, Field::taxonomyTerm('s')->taxonomy('category')->multiple(false)->sanitizeForStorage('8'));
        } finally {
            unset($GLOBALS['_wp_terms']);
        }
    }
}
