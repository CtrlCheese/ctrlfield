<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Data;

use CtrlField\Data\FieldDataService;
use PHPUnit\Framework\TestCase;

class FieldDataServiceTest extends TestCase
{
    public function test_getInstance_returns_same_instance(): void
    {
        $a = FieldDataService::getInstance();
        $b = FieldDataService::getInstance();

        $this->assertSame($a, $b);
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        // PostMetaAdapter will call get_post_meta which returns '' via stubs,
        // so load() returns null → getAll() returns [] → get() returns null.
        $result = FieldDataService::getInstance()->get('nonexistent_key', 1, 'post');

        $this->assertNull($result);
    }

    public function test_getAll_returns_empty_array_when_no_data(): void
    {
        $result = FieldDataService::getInstance()->getAll(1, 'post');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_getAll_returns_empty_array_for_user(): void
    {
        $result = FieldDataService::getInstance()->getAll(1, 'user');

        $this->assertIsArray($result);
    }

    public function test_getAll_returns_empty_array_for_term(): void
    {
        $result = FieldDataService::getInstance()->getAll(1, 'term');

        $this->assertIsArray($result);
    }

    public function test_getAll_returns_empty_array_for_comment(): void
    {
        $result = FieldDataService::getInstance()->getAll(1, 'comment');

        $this->assertIsArray($result);
    }

    public function test_getAll_unknown_entity_type_falls_back_to_post(): void
    {
        // Unknown type defaults to PostMetaAdapter — no exception.
        $result = FieldDataService::getInstance()->getAll(1, 'unknown_type');

        $this->assertIsArray($result);
    }
}
