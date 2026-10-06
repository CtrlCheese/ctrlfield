<?php

declare(strict_types=1);

namespace CtrlField\Fields\Notifications;

/**
 * Immutable config for a single field-change notification rule.
 */
final class FieldNotificationConfig
{
    /**
     * @param string                        $toValue  Trigger only when new value equals this string.
     *                                                Empty string = trigger on any change.
     * @param string|string[]|\Closure      $to       Recipient(s) or a callable(int $postId): string|string[].
     * @param string                        $subject  Email subject (supports {post_title}, {post_id}, etc.).
     * @param string                        $message  Email body (same placeholders).
     */
    public function __construct(
        public readonly string $toValue,
        public readonly string|array|\Closure $to,
        public readonly string $subject,
        public readonly string $message,
    ) {}
}
