<?php

declare(strict_types=1);

namespace CtrlField\Core\Notifications;

use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Notifications\FieldNotificationConfig;

/**
 * Sends emails when a field value changes to a configured trigger value.
 *
 * Hooked onto ctrlfield/after_save via MetaBoxServiceProvider.
 * Reads old values BEFORE the save and compares against new values.
 *
 * Excluded from PHPStan — references wp_mail and WP helper functions.
 */
final class NotificationDispatcher
{
    /**
     * @param array<string, mixed> $oldFields  Field values before the save.
     * @param array<string, mixed> $newFields  Field values after the save.
     * @param FieldGroup[]         $groups     All groups relevant to this post.
     */
    public function dispatch(int $postId, array $oldFields, array $newFields, array $groups): void
    {
        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $notifications = $field->getNotifications();
                if (empty($notifications)) {
                    continue;
                }

                $key      = $field->getKey();
                $oldValue = $oldFields[$key] ?? null;
                $newValue = $newFields[$key] ?? null;

                if ($oldValue === $newValue) {
                    continue;
                }

                foreach ($notifications as $config) {
                    $this->maybeNotify($postId, $key, $oldValue, $newValue, $config);
                }
            }
        }
    }

    private function maybeNotify(
        int   $postId,
        string $key,
        mixed $oldValue,
        mixed $newValue,
        FieldNotificationConfig $config,
    ): void {
        if ($config->toValue !== '' && (string) $newValue !== $config->toValue) {
            return;
        }

        $to = is_callable($config->to)
            ? ($config->to)($postId)
            : $config->to;

        $replacements = $this->buildReplacements($postId, $key, $oldValue, $newValue);
        $subject      = $this->interpolate($config->subject, $replacements);
        $message      = $this->interpolate($config->message, $replacements);

        if (function_exists('wp_mail')) {
            wp_mail($to, $subject, $message);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildReplacements(int $postId, string $key, mixed $old, mixed $new): array
    {
        $post       = function_exists('get_post') ? get_post($postId) : null;
        $postTitle  = $post?->post_title ?? '';
        $adminUrl   = function_exists('get_edit_post_link') ? (string) get_edit_post_link($postId) : '';
        $date       = function_exists('date_i18n') ? date_i18n('Y-m-d H:i') : date('Y-m-d H:i');

        return [
            '{post_title}' => $postTitle,
            '{post_id}'    => (string) $postId,
            '{field_key}'  => $key,
            '{old_value}'  => is_scalar($old) ? (string) $old : '',
            '{new_value}'  => is_scalar($new) ? (string) $new : '',
            '{date}'       => $date,
            '{admin_url}'  => $adminUrl,
        ];
    }

    /** @param array<string, string> $replacements */
    private function interpolate(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
