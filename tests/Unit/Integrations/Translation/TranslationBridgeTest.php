<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Integrations\Translation;

use FieldForge\Integrations\Translation\TranslationBridge;
use PHPUnit\Framework\TestCase;

/**
 * TranslationBridge is tested in an environment without WPML or Polylang.
 * We can only verify the detection logic and the fallback behaviour.
 */
class TranslationBridgeTest extends TestCase
{
    public function test_is_wpml_active_returns_false_without_wpml(): void
    {
        $this->assertFalse(TranslationBridge::isWpmlActive());
    }

    public function test_is_polylang_active_returns_false_without_polylang(): void
    {
        $this->assertFalse(TranslationBridge::isPolylangActive());
    }

    public function test_is_active_returns_false_when_neither_plugin_present(): void
    {
        $this->assertFalse(TranslationBridge::isActive());
    }

    public function test_get_current_language_returns_empty_string_without_plugin(): void
    {
        $this->assertSame('', TranslationBridge::getCurrentLanguage());
    }

    public function test_get_original_post_id_returns_same_id_without_plugin(): void
    {
        $this->assertSame(42, TranslationBridge::getOriginalPostId(42));
    }

    public function test_get_translations_returns_empty_array_without_plugin(): void
    {
        $this->assertSame([], TranslationBridge::getTranslations(42));
    }
}
