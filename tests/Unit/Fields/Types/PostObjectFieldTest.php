<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Fields\Contracts\FieldSanitizerInterface;
use CtrlField\Fields\Types\PostObjectField;
use PHPUnit\Framework\TestCase;

class PostObjectFieldTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_wp_posts'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_posts']);
    }

    public function testFieldType(): void
    {
        $field = new PostObjectField('client');
        self::assertSame(FieldType::POST_OBJECT, $field->getType());
    }

    public function testImplementsInterfaces(): void
    {
        $field = new PostObjectField('client');
        self::assertInstanceOf(FieldSanitizerInterface::class, $field);
        self::assertInstanceOf(CollectionConstraintsInterface::class, $field);
    }

    public function testMinMaxPassThroughToInterface(): void
    {
        $field = (new PostObjectField('client'))->minPosts(2)->maxPosts(5);
        self::assertSame(2, $field->getMinItems());
        self::assertSame(5, $field->getMaxItems());
    }

    // -------------------------------------------------------------------------
    // Sanitization (AC 5)
    // -------------------------------------------------------------------------

    public function testSanitizeStripsMissingPost(): void
    {
        $field = (new PostObjectField('client'))->multiple(false);
        $result = $field->sanitizeForStorage(99); // post 99 not in _wp_posts
        self::assertSame(0, $result);
    }

    public function testSanitizeSingleReturnsIdWhenPostExists(): void
    {
        $post      = new \WP_Post();
        $post->ID  = 42;
        $GLOBALS['_wp_posts'][42] = $post;

        $field  = (new PostObjectField('client'))->multiple(false);
        $result = $field->sanitizeForStorage(42);
        self::assertSame(42, $result);
    }

    public function testSanitizeMultipleStripsInvalidIds(): void
    {
        $post      = new \WP_Post();
        $post->ID  = 10;
        $GLOBALS['_wp_posts'][10] = $post;

        $field  = (new PostObjectField('clients'))->multiple(true);
        $result = $field->sanitizeForStorage([10, 99, 999]); // 99 and 999 don't exist
        self::assertSame([10], $result);
    }

    public function testSanitizeMultipleReturnsEmptyForNoValidIds(): void
    {
        $field  = (new PostObjectField('clients'))->multiple(true);
        $result = $field->sanitizeForStorage([77, 88]);
        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // returnFormat (AC 4)
    // -------------------------------------------------------------------------

    public function testReturnFormatDefault(): void
    {
        $field = new PostObjectField('client');
        self::assertSame('id', $field->getReturnFormat());
    }

    public function testReturnFormatObject(): void
    {
        $field = (new PostObjectField('client'))->returnFormat('object');
        self::assertSame('object', $field->getReturnFormat());
    }

    // -------------------------------------------------------------------------
    // Storage format (AC 3)
    // -------------------------------------------------------------------------

    public function testSingleStoresInteger(): void
    {
        $post      = new \WP_Post();
        $post->ID  = 42;
        $GLOBALS['_wp_posts'][42] = $post;

        $field  = (new PostObjectField('client'))->multiple(false);
        $result = $field->sanitizeForStorage(42);
        self::assertIsInt($result); // AC3: stores int, not string
        self::assertSame(42, $result);
    }
}
