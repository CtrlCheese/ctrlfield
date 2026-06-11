<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Core\Notifications;

use FieldForge\Core\Notifications\NotificationDispatcher;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class NotificationDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
        $GLOBALS['_ff_wp_mail_calls'] = [];
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
        unset($GLOBALS['_ff_wp_mail_calls']);
    }

    public function test_dispatch_sends_email_when_value_matches(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::select('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved'])
                    ->notifyOnChange('approved', 'hr@company.com', 'Approved: {post_title}', 'Post {post_id}.'),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(
            42,
            ['status' => 'pending'],
            ['status' => 'approved'],
            [$group],
        );

        $calls = $GLOBALS['_ff_wp_mail_calls'] ?? [];
        $this->assertCount(1, $calls);
        $this->assertSame('hr@company.com', $calls[0]['to']);
    }

    public function test_dispatch_does_not_send_when_no_change(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::select('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved'])
                    ->notifyOnChange('approved', 'hr@company.com', 'Sub', 'Msg'),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(42, ['status' => 'approved'], ['status' => 'approved'], [$group]);

        $this->assertEmpty($GLOBALS['_ff_wp_mail_calls'] ?? []);
    }

    public function test_dispatch_does_not_send_when_value_does_not_match_to_value(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::select('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->notifyOnChange('approved', 'hr@company.com', 'Sub', 'Msg'),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        // Changed to 'rejected', not 'approved'
        $dispatcher->dispatch(42, ['status' => 'pending'], ['status' => 'rejected'], [$group]);

        $this->assertEmpty($GLOBALS['_ff_wp_mail_calls'] ?? []);
    }

    public function test_dispatch_sends_with_callable_to(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::select('status')
                    ->options(['approved' => 'Approved'])
                    ->notifyOnChange(
                        'approved',
                        static fn(int $postId) => 'user' . $postId . '@test.com',
                        'Sub',
                        'Msg',
                    ),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(99, ['status' => 'pending'], ['status' => 'approved'], [$group]);

        $calls = $GLOBALS['_ff_wp_mail_calls'] ?? [];
        $this->assertCount(1, $calls);
        $this->assertSame('user99@test.com', $calls[0]['to']);
    }

    public function test_dispatch_sends_when_to_value_is_empty_for_any_change(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::text('title')
                    ->notifyOnChange('', 'admin@example.com', 'Changed', 'Something changed.'),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(1, ['title' => 'Old'], ['title' => 'New'], [$group]);

        $calls = $GLOBALS['_ff_wp_mail_calls'] ?? [];
        $this->assertCount(1, $calls);
    }

    public function test_subject_interpolation(): void
    {
        $group = Field::group('app')
            ->where('post_type', '==', 'application')
            ->fields([
                Field::select('status')
                    ->options(['approved' => 'Approved'])
                    ->notifyOnChange('approved', 'hr@company.com', 'Approved post {post_id}', 'Msg'),
            ]);
        $group->register();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(42, ['status' => 'pending'], ['status' => 'approved'], [$group]);

        $calls = $GLOBALS['_ff_wp_mail_calls'] ?? [];
        $this->assertCount(1, $calls);
        $this->assertSame('Approved post 42', $calls[0]['subject']);
    }
}
