<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\Translation;

use CtrlField\Integrations\Translation\TranslationSyncHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TranslationSyncHandler.
 * In the test environment neither WPML nor Polylang is active, so all sync
 * methods are no-ops. We verify they do NOT throw and accept the correct args.
 */
class TranslationSyncHandlerTest extends TestCase
{
    private TranslationSyncHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TranslationSyncHandler();
    }

    public function test_sync_shared_fields_is_noop_when_no_translation_plugin(): void
    {
        // Should not throw even with arbitrary data.
        $this->handler->syncSharedFields(1, ['client_name' => 'Acme']);
        $this->assertTrue(true); // reached
    }

    public function test_on_polylang_copy_is_noop_when_no_stored_data(): void
    {
        // fromId 0 → adapter load returns null → no save → no throw.
        $this->handler->onPolylangCopy(0, false, 99);
        $this->assertTrue(true);
    }

    public function test_on_wpml_copy_is_noop_when_no_stored_data(): void
    {
        $this->handler->onWpmlCopy(0, [], 99);
        $this->assertTrue(true);
    }
}
