<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Fields\Notifications;

use CtrlField\Fields\Field;
use CtrlField\Fields\Notifications\FieldNotificationConfig;
use PHPUnit\Framework\TestCase;

class NotificationConfigTest extends TestCase
{
    public function test_notify_on_change_stores_config(): void
    {
        $field = Field::select('status')
            ->options(['pending' => 'Pending', 'approved' => 'Approved'])
            ->notifyOnChange(
                toValue: 'approved',
                to:      'hr@company.com',
                subject: 'Application approved',
                message: 'Post {post_id} was approved.',
            );

        $notifications = $field->getNotifications();

        $this->assertCount(1, $notifications);
        $this->assertInstanceOf(FieldNotificationConfig::class, $notifications[0]);
        $this->assertSame('approved', $notifications[0]->toValue);
        $this->assertSame('hr@company.com', $notifications[0]->to);
    }

    public function test_multiple_notifications_are_stored(): void
    {
        $field = Field::select('status')
            ->options(['a' => 'A', 'b' => 'B'])
            ->notifyOnChange('a', 'a@example.com', 'Sub A', 'Msg A')
            ->notifyOnChange('b', 'b@example.com', 'Sub B', 'Msg B');

        $this->assertCount(2, $field->getNotifications());
    }

    public function test_notify_with_callable_to(): void
    {
        $field = Field::select('status')
            ->options(['approved' => 'Approved'])
            ->notifyOnChange(
                toValue: 'approved',
                to:      static fn(int $postId) => 'user' . $postId . '@example.com',
                subject: 'Status changed',
                message: 'Changed.',
            );

        $config = $field->getNotifications()[0];
        $this->assertIsCallable($config->to);
        $this->assertSame('user42@example.com', ($config->to)(42));
    }

    public function test_notifications_default_empty(): void
    {
        $field = Field::text('name');
        $this->assertEmpty($field->getNotifications());
    }

    public function test_empty_to_value_means_any_change(): void
    {
        $field = Field::text('title')
            ->notifyOnChange(
                toValue: '',
                to:      'admin@example.com',
                subject: 'Title changed',
                message: 'It changed.',
            );

        $config = $field->getNotifications()[0];
        $this->assertSame('', $config->toValue);
    }
}
