<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Registry;

use CtrlField\Builder\AdminContext;
use CtrlField\Fields\Field;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Registry\Contracts\ContextInterface;
use CtrlField\Registry\Exceptions\DuplicateContextKeyException;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class ContextRegistryV2Test extends TestCase
{
    protected function tearDown(): void
    {
        FieldRegistry::reset();
        ContextRegistry::reset();
    }

    // -------------------------------------------------------------------------
    // New context keys resolve correctly
    // -------------------------------------------------------------------------

    public function test_taxonomy_context_resolves(): void
    {
        Field::group('term_group')
            ->where('taxonomy', '==', 'category')
            ->fields([Field::text('description')])
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(taxonomy: 'category'));
        $this->assertArrayHasKey('term_group', $matches);
    }

    public function test_taxonomy_context_no_match_for_different_taxonomy(): void
    {
        Field::group('term_group')
            ->where('taxonomy', '==', 'category')
            ->fields([Field::text('description')])
            ->register();

        $this->assertEmpty(ContextRegistry::resolve(new AdminContext(taxonomy: 'tag')));
    }

    public function test_user_profile_context_resolves(): void
    {
        Field::group('user_group')
            ->where('context', '==', 'user_profile')
            ->fields([Field::text('bio')])
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(contextType: 'user_profile'));
        $this->assertArrayHasKey('user_group', $matches);
    }

    public function test_user_profile_context_not_matches_comment(): void
    {
        Field::group('user_group')
            ->where('context', '==', 'user_profile')
            ->fields([Field::text('bio')])
            ->register();

        $this->assertEmpty(ContextRegistry::resolve(new AdminContext(contextType: 'comment')));
    }

    public function test_comment_context_resolves(): void
    {
        Field::group('comment_group')
            ->where('context', '==', 'comment')
            ->fields([Field::text('sentiment')])
            ->register();

        $matches = ContextRegistry::resolve(new AdminContext(contextType: 'comment'));
        $this->assertArrayHasKey('comment_group', $matches);
    }

    // -------------------------------------------------------------------------
    // ContextRegistry::register() — custom third-party resolvers
    // -------------------------------------------------------------------------

    public function test_register_custom_resolver(): void
    {
        $resolver = new class implements ContextInterface {
            public static function matches(mixed $screen, string $operator, mixed $value): bool
            {
                return $screen instanceof AdminContext && $screen->postType === 'product';
            }
        };

        ContextRegistry::register('woocommerce_product', $resolver::class);

        $this->assertTrue(ContextRegistry::isValidKey('woocommerce_product'));
    }

    public function test_duplicate_builtin_key_throws(): void
    {
        $this->expectException(DuplicateContextKeyException::class);

        ContextRegistry::register('post_type', \stdClass::class);
    }

    public function test_duplicate_custom_key_throws(): void
    {
        $this->expectException(DuplicateContextKeyException::class);

        $fakeClass = new class implements ContextInterface {
            public static function matches(mixed $screen, string $operator, mixed $value): bool { return false; }
        };

        ContextRegistry::register('my_custom_key', $fakeClass::class);
        ContextRegistry::register('my_custom_key', $fakeClass::class); // second throws
    }

    public function test_reset_clears_custom_but_not_builtin(): void
    {
        $fakeClass = new class implements ContextInterface {
            public static function matches(mixed $screen, string $operator, mixed $value): bool { return false; }
        };

        ContextRegistry::register('temp_key', $fakeClass::class);
        $this->assertTrue(ContextRegistry::isValidKey('temp_key'));

        ContextRegistry::reset();
        $this->assertFalse(ContextRegistry::isValidKey('temp_key'));

        // Built-ins survive reset
        $this->assertTrue(ContextRegistry::isValidKey('post_type'));
        $this->assertTrue(ContextRegistry::isValidKey('taxonomy'));
        $this->assertTrue(ContextRegistry::isValidKey('context'));
    }

    // -------------------------------------------------------------------------
    // StorageAdapterResolver
    // -------------------------------------------------------------------------

    public function test_adapter_resolver_post_type(): void
    {
        $group = Field::group('pg')->where('post_type', '==', 'post')->fields([]);
        $adapter = \CtrlField\Storage\StorageAdapterResolver::resolve($group);
        $this->assertInstanceOf(\CtrlField\Storage\PostMetaAdapter::class, $adapter);
    }

    public function test_adapter_resolver_user_profile(): void
    {
        $group = Field::group('ug')->where('context', '==', 'user_profile')->fields([]);
        $adapter = \CtrlField\Storage\StorageAdapterResolver::resolve($group);
        $this->assertInstanceOf(\CtrlField\Storage\UserMetaAdapter::class, $adapter);
    }

    public function test_adapter_resolver_comment(): void
    {
        $group = Field::group('cg')->where('context', '==', 'comment')->fields([]);
        $adapter = \CtrlField\Storage\StorageAdapterResolver::resolve($group);
        $this->assertInstanceOf(\CtrlField\Storage\CommentMetaAdapter::class, $adapter);
    }

    public function test_adapter_resolver_taxonomy(): void
    {
        $group = Field::group('tg')->where('taxonomy', '==', 'category')->fields([]);
        $adapter = \CtrlField\Storage\StorageAdapterResolver::resolve($group);
        $this->assertInstanceOf(\CtrlField\Storage\TermMetaAdapter::class, $adapter);
    }

    public function test_adapter_resolver_throws_for_mixed_backends(): void
    {
        $this->expectException(\CtrlField\Storage\Exceptions\UnresolvableAdapterException::class);

        // A group spanning post and user contexts is ambiguous
        $group = Field::group('mixed')
            ->whereAny([
                ['post_type', '==', 'portfolio'],
                ['context', '==', 'user_profile'],
            ])
            ->fields([]);

        \CtrlField\Storage\StorageAdapterResolver::resolve($group);
    }
}
