<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\CLI\Scaffold;

use CtrlField\Enums\FieldType;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\InvalidSlugException;
use CtrlField\Integrations\CLI\Scaffold\Exceptions\UnknownFieldTypeException;
use CtrlField\Integrations\CLI\Scaffold\FieldScaffoldDef;
use PHPUnit\Framework\TestCase;

class FieldScaffoldDefTest extends TestCase
{
    public function testParseTokenWithLabel(): void
    {
        $def = FieldScaffoldDef::fromToken('client_name:text:Client Name');

        self::assertSame('client_name', $def->key);
        self::assertSame(FieldType::TEXT, $def->type);
        self::assertSame('Client Name', $def->label);
    }

    public function testParseTokenWithoutLabel(): void
    {
        $def = FieldScaffoldDef::fromToken('budget:number');

        self::assertSame('budget', $def->key);
        self::assertSame(FieldType::NUMBER, $def->type);
        self::assertSame('Budget', $def->label);
    }

    public function testTitleCaseLabelFromKey(): void
    {
        $def = FieldScaffoldDef::fromToken('project_type:select');
        self::assertSame('Project Type', $def->label);
    }

    public function testInvalidKeyThrows(): void
    {
        $this->expectException(InvalidSlugException::class);
        FieldScaffoldDef::fromToken('BadKey:text');
    }

    public function testKeyWithDashThrows(): void
    {
        $this->expectException(InvalidSlugException::class);
        FieldScaffoldDef::fromToken('my-key:text');
    }

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(UnknownFieldTypeException::class);
        FieldScaffoldDef::fromToken('title:nonexistent_type');
    }

    public function testParseManyReturnsMultipleFields(): void
    {
        $defs = FieldScaffoldDef::parseMany('client_name:text:Client,budget:number:Budget');

        self::assertCount(2, $defs);
        self::assertSame('client_name', $defs[0]->key);
        self::assertSame('Client', $defs[0]->label);
        self::assertSame('budget', $defs[1]->key);
        self::assertSame('Budget', $defs[1]->label);
    }

    public function testParseManyEmptyReturnsEmpty(): void
    {
        self::assertSame([], FieldScaffoldDef::parseMany(''));
    }

    public function testAllFieldTypesAreRecognised(): void
    {
        foreach (FieldType::cases() as $case) {
            $def = FieldScaffoldDef::fromToken("my_field:{$case->value}");
            self::assertSame($case, $def->type);
        }
    }
}
