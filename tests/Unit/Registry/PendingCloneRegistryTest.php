<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Registry;

use FieldForge\Builder\FieldGroup;
use FieldForge\Fields\Exceptions\UnresolvedCloneException;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use FieldForge\Registry\PendingCloneRegistry;
use PHPUnit\Framework\TestCase;

class PendingCloneRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        PendingCloneRegistry::reset();
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        PendingCloneRegistry::reset();
        FieldRegistry::reset();
    }

    // -------------------------------------------------------------------------
    // Basic state
    // -------------------------------------------------------------------------

    public function test_has_pending_returns_false_when_empty(): void
    {
        self::assertFalse(PendingCloneRegistry::hasPending());
    }

    public function test_has_pending_returns_true_after_defer(): void
    {
        $group = FieldGroup::make('consumer')->fields([]);

        PendingCloneRegistry::defer('missing_source', $group);

        self::assertTrue(PendingCloneRegistry::hasPending());
    }

    public function test_get_pending_returns_empty_map_when_none(): void
    {
        self::assertSame([], PendingCloneRegistry::getPending());
    }

    public function test_get_pending_returns_deferred_groups(): void
    {
        $group = FieldGroup::make('consumer')->fields([]);

        PendingCloneRegistry::defer('address_fields', $group);

        $pending = PendingCloneRegistry::getPending();

        self::assertArrayHasKey('address_fields', $pending);
        self::assertCount(1, $pending['address_fields']);
        self::assertSame($group, $pending['address_fields'][0]);
    }

    public function test_defer_accumulates_multiple_groups_for_same_source(): void
    {
        $group1 = FieldGroup::make('consumer1')->fields([]);
        $group2 = FieldGroup::make('consumer2')->fields([]);

        PendingCloneRegistry::defer('address_fields', $group1);
        PendingCloneRegistry::defer('address_fields', $group2);

        $pending = PendingCloneRegistry::getPending();

        self::assertCount(2, $pending['address_fields']);
    }

    // -------------------------------------------------------------------------
    // reset()
    // -------------------------------------------------------------------------

    public function test_reset_clears_all_pending(): void
    {
        $group = FieldGroup::make('consumer')->fields([]);
        PendingCloneRegistry::defer('source', $group);

        PendingCloneRegistry::reset();

        self::assertFalse(PendingCloneRegistry::hasPending());
        self::assertSame([], PendingCloneRegistry::getPending());
    }

    // -------------------------------------------------------------------------
    // resolve()
    // -------------------------------------------------------------------------

    public function test_resolve_does_nothing_when_no_groups_waiting(): void
    {
        // Should not throw
        PendingCloneRegistry::resolve('nonexistent_source');

        self::assertFalse(PendingCloneRegistry::hasPending());
    }

    public function test_resolve_removes_the_pending_entry(): void
    {
        $group = FieldGroup::make('consumer')->fields([]);
        PendingCloneRegistry::defer('address_fields', $group);

        // Register the source group so the deferred consumer can succeed
        FieldGroup::make('address_fields')
            ->fields([Field::text('street')])
            ->register();

        // resolve() is called automatically by FieldGroup::register(), but we can
        // also confirm the side-effect: the pending entry should be gone.
        self::assertFalse(PendingCloneRegistry::hasPending());
    }

    public function test_resolve_triggers_registration_of_deferred_group(): void
    {
        // Register the library group first to establish that it's available after deferred consumer
        FieldGroup::make('contact_fields')
            ->fields([
                Field::text('phone')->label('Phone'),
            ])
            ->register();

        // Force a group into pending manually (simulates out-of-order loading)
        $consumer = FieldGroup::make('profile_details')
            ->where('post_type', '==', 'profile')
            ->fields([]);

        PendingCloneRegistry::defer('contact_fields', $consumer);

        // Resolve should trigger $consumer->register(), which should add it to FieldRegistry
        PendingCloneRegistry::resolve('contact_fields');

        // Consumer group now appears in FieldRegistry
        self::assertTrue(FieldRegistry::has('profile_details'));
        self::assertFalse(PendingCloneRegistry::hasPending());
    }

    // -------------------------------------------------------------------------
    // assertNoPending()
    // -------------------------------------------------------------------------

    public function test_assert_no_pending_passes_when_empty(): void
    {
        // Should not throw
        PendingCloneRegistry::assertNoPending();

        self::assertTrue(true); // reached here without exception
    }

    public function test_assert_no_pending_throws_unresolved_clone_exception(): void
    {
        $group = FieldGroup::make('consumer')->fields([]);
        PendingCloneRegistry::defer('missing_library', $group);

        $this->expectException(UnresolvedCloneException::class);
        $this->expectExceptionMessageMatches('/consumer/');
        $this->expectExceptionMessageMatches('/missing_library/');

        PendingCloneRegistry::assertNoPending();
    }

    public function test_assert_no_pending_message_includes_all_pending_groups(): void
    {
        PendingCloneRegistry::defer('lib_a', FieldGroup::make('group_1')->fields([]));
        PendingCloneRegistry::defer('lib_b', FieldGroup::make('group_2')->fields([]));

        try {
            PendingCloneRegistry::assertNoPending();
            self::fail('Expected UnresolvedCloneException was not thrown.');
        } catch (UnresolvedCloneException $e) {
            self::assertStringContainsString('group_1', $e->getMessage());
            self::assertStringContainsString('lib_a', $e->getMessage());
            self::assertStringContainsString('group_2', $e->getMessage());
            self::assertStringContainsString('lib_b', $e->getMessage());
        }
    }
}
