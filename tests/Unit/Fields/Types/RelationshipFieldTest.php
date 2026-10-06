<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Types;

use CtrlField\Enums\FieldType;
use CtrlField\Fields\Contracts\CollectionConstraintsInterface;
use CtrlField\Fields\Contracts\ExternalStorageInterface;
use CtrlField\Fields\Types\RelationshipField;
use PHPUnit\Framework\TestCase;

class RelationshipFieldTest extends TestCase
{
    public function testFieldType(): void
    {
        $field = new RelationshipField('team_members');
        self::assertSame(FieldType::RELATIONSHIP, $field->getType());
    }

    public function testImplementsInterfaces(): void
    {
        $field = new RelationshipField('team_members');
        self::assertInstanceOf(ExternalStorageInterface::class, $field);
        self::assertInstanceOf(CollectionConstraintsInterface::class, $field);
    }

    public function testMinMaxItems(): void
    {
        $field = (new RelationshipField('members'))->minItems(1)->maxItems(10);
        self::assertSame(1, $field->getMinItems());
        self::assertSame(10, $field->getMaxItems());
    }

    public function testBidirectionalDefault(): void
    {
        $field = new RelationshipField('members');
        self::assertFalse($field->isBidirectional());
    }

    public function testBidirectionalCanBeEnabled(): void
    {
        $field = (new RelationshipField('members'))->bidirectional();
        self::assertTrue($field->isBidirectional());
    }
}
