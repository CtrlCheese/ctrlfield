<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Translation;

use CtrlField\Builder\AdminContext;
use CtrlField\Core\Migration\SchemaVersion;
use CtrlField\Registry\ContextRegistry;
use CtrlField\Storage\Drivers\WpPostMetaDriver;
use CtrlField\Storage\PostMetaAdapter;

/**
 * Handles synchronisation of shared (non-translatable) fields across translations,
 * and copies _ctrlfield_data when a new translation is created.
 *
 * Excluded from PHPStan — references WP functions.
 */
final class TranslationSyncHandler
{
    /**
     * Static guard against re-entrant saves when syncing shared fields.
     *
     * @var array<int, true>
     */
    private static array $saving = [];

    /**
     * Fired on `ctrlfield/after_save`.
     * Writes shared field values to all sibling translations.
     *
     * @param array<string, mixed> $savedFields  The flat fields array from PipelineContext::$fields.
     */
    public function syncSharedFields(int $postId, array $savedFields): void
    {
        if (! TranslationBridge::isActive()) {
            return;
        }

        if (isset(self::$saving[$postId])) {
            return;
        }

        $translations = TranslationBridge::getTranslations($postId);
        if (count($translations) <= 1) {
            return;
        }

        $sharedKeys = $this->resolveSharedFieldKeys($postId);
        if (empty($sharedKeys)) {
            return;
        }

        $adapter = new PostMetaAdapter(new WpPostMetaDriver());

        foreach ($translations as $langCode => $translationId) {
            if ($translationId === $postId) {
                continue;
            }

            self::$saving[$translationId] = true;

            try {
                $existing = $adapter->load($translationId) ?? [];
                foreach ($sharedKeys as $fieldKey) {
                    if (array_key_exists($fieldKey, $savedFields)) {
                        $existing[$fieldKey] = $savedFields[$fieldKey];
                    }
                }
                $adapter->save($translationId, $existing, SchemaVersion::CURRENT);
            } finally {
                unset(self::$saving[$translationId]);
            }
        }
    }

    /**
     * Fired on `pll_after_copy` — copies _ctrlfield_data when Polylang creates a translation.
     */
    public function onPolylangCopy(int $fromId, bool $isSyncEnabled, int $toId): void
    {
        $this->copyData($fromId, $toId);
    }

    /**
     * Fired on `wpml_after_copy_meta` — copies _ctrlfield_data when WPML creates a translation.
     *
     * @param array<string, mixed> $postedData
     */
    public function onWpmlCopy(int $masterPost, array $postedData, int $translationId): void
    {
        $this->copyData($masterPost, $translationId);
    }

    // -------------------------------------------------------------------------

    private function copyData(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            return;
        }

        $adapter = new PostMetaAdapter(new WpPostMetaDriver());
        $data    = $adapter->load($fromId);

        if ($data !== null) {
            $adapter->save($toId, $data, SchemaVersion::CURRENT);
        }
    }

    /**
     * Returns field keys with ->translate(false) from all groups that apply to the given post.
     *
     * @return string[]
     */
    private function resolveSharedFieldKeys(int $postId): array
    {
        if (! function_exists('get_post_type')) {
            return [];
        }

        $postType = get_post_type($postId);
        if ($postType === false || ! is_string($postType)) {
            return [];
        }

        $groups     = ContextRegistry::resolve(AdminContext::forPost($postId));
        $sharedKeys = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                if (! $field->isTranslatable()) {
                    $sharedKeys[] = $field->getKey();
                }
            }
        }

        return $sharedKeys;
    }
}
