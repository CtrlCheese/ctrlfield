<?php

declare(strict_types=1);

namespace CtrlField\Admin;

use CtrlField\Admin\MetaBox\MetaBoxServiceProvider;
use CtrlField\Builder\AdminContext;
use CtrlField\Core\Pipeline\PipelineException;
use CtrlField\Core\Pipeline\SavePipeline;
use CtrlField\Storage\Contracts\StorageAdapterInterface;

/**
 * Saves the CtrlField fields of a user, term or comment edit form.
 *
 * Shared by the user / term / comment registrars so a bad or missing payload
 * never takes the whole admin request down:
 * - no payload, an empty one (the field script did not run) or one without
 *   fields → nothing is saved, the rest of the WordPress form saves normally;
 * - a pipeline error (validation, nonce, capability) → kept for one minute
 *   and shown as an admin notice on the next load of the edit screen.
 *
 * Excluded from PHPStan — references WP functions.
 */
final class EntitySave
{
    /**
     * @param string $scope 'user' | 'term' | 'comment' (part of the error key)
     */
    public static function run(
        string $scope,
        int $entityId,
        StorageAdapterInterface $adapter,
        AdminContext $context,
        string $objectCapability,
    ): void {
        // The CtrlField nonce is checked by the pipeline (NonceValidationStage), before anything is stored.
        $fields = self::mergedPayload($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($fields === null) {
            return;
        }

        try {
            SavePipeline::run(
                entityId:         $entityId,
                rawPost:          array_merge($_POST, [ // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by NonceValidationStage
                    'ctrlfield_payload' => wp_slash((string) wp_json_encode($fields)),
                ]),
                adapterOverride:  $adapter,
                contextOverride:  $context,
                capability:       '',
                objectCapability: $objectCapability,
            );
        } catch (PipelineException $e) {
            set_transient(self::errorKey($scope, $entityId), MetaBoxServiceProvider::friendlyError($e), MINUTE_IN_SECONDS);
        }
    }

    /**
     * The field values of the request: one JSON payload (legacy renderer) or one
     * per group (`ctrlfield_payload[group]`), merged. Null when there is nothing
     * to save.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>|null
     */
    public static function mergedPayload(array $post): ?array
    {
        $raw = $post['ctrlfield_payload'] ?? null;
        $payloads = match (true) {
            is_string($raw) => [$raw],
            is_array($raw)  => array_filter($raw, 'is_string'),
            default         => [],
        };

        $merged = [];
        foreach ($payloads as $json) {
            $decoded = json_decode(wp_unslash($json), true);
            if (is_array($decoded)) {
                $merged = array_merge($merged, $decoded);
            }
        }

        return $merged === [] ? null : $merged;
    }

    public static function errorKey(string $scope, int $entityId): string
    {
        return 'ctrlfield_save_error_' . get_current_user_id() . '_' . $scope . '_' . $entityId;
    }

    /** Prints (once) the error of the last save of this entity, if any. */
    public static function printError(string $scope, int $entityId): void
    {
        if ($entityId <= 0) {
            return;
        }
        $key   = self::errorKey($scope, $entityId);
        $error = get_transient($key);
        if (! is_string($error) || $error === '') {
            return;
        }
        delete_transient($key);

        printf(
            '<div class="notice notice-error is-dismissible"><p><strong>CtrlField:</strong> %s</p></div>',
            esc_html($error),
        );
    }
}
