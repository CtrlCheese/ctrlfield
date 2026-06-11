<?php

declare(strict_types=1);

namespace FieldForge\Integrations\Translation;

/**
 * Abstraction layer over WPML and Polylang.
 * No hard dependency on either plugin — all calls are gated behind runtime checks.
 * Excluded from PHPStan — references WPML/Polylang functions that are not declared.
 */
final class TranslationBridge
{
    public static function isWpmlActive(): bool
    {
        return defined('ICL_LANGUAGE_CODE') && function_exists('apply_filters');
    }

    public static function isPolylangActive(): bool
    {
        return function_exists('pll_current_language');
    }

    public static function isActive(): bool
    {
        return self::isWpmlActive() || self::isPolylangActive();
    }

    public static function getCurrentLanguage(): string
    {
        if (self::isWpmlActive() && defined('ICL_LANGUAGE_CODE')) {
            return (string) ICL_LANGUAGE_CODE;
        }

        if (self::isPolylangActive()) {
            /** @phpstan-ignore-next-line */
            return (string)(pll_current_language() ?: '');
        }

        return '';
    }

    public static function getOriginalPostId(int $translatedId): int
    {
        if (self::isWpmlActive() && function_exists('get_post_type') && function_exists('apply_filters')) {
            $postType = get_post_type($translatedId);
            if (is_string($postType)) {
                $defaultLang = apply_filters('wpml_default_language', null);
                /** @phpstan-ignore-next-line */
                $original = apply_filters('wpml_object_id', $translatedId, $postType, false, $defaultLang);
                if (is_int($original) && $original > 0) {
                    return $original;
                }
            }
        }

        if (self::isPolylangActive() && function_exists('pll_default_language') && function_exists('pll_get_post')) {
            /** @phpstan-ignore-next-line */
            $default = pll_default_language();
            if (is_string($default) && $default !== '') {
                /** @phpstan-ignore-next-line */
                $original = pll_get_post($translatedId, $default);
                if (is_int($original) && $original > 0) {
                    return $original;
                }
            }
        }

        return $translatedId;
    }

    /**
     * Returns all post IDs that are translations of each other (including the given ID).
     *
     * @return array<string, int>  ['lang_code' => post_id, ...]
     */
    public static function getTranslations(int $postId): array
    {
        if (self::isWpmlActive() && function_exists('get_post_type') && function_exists('apply_filters')) {
            $postType = get_post_type($postId);
            if (is_string($postType)) {
                /** @phpstan-ignore-next-line */
                $details = apply_filters('wpml_element_translations', null, $postId, $postType);
                if (is_array($details)) {
                    $result = [];
                    foreach ($details as $langCode => $info) {
                        if (is_array($info) && isset($info['element_id'])) {
                            $result[(string) $langCode] = (int) $info['element_id'];
                        }
                    }
                    return $result;
                }
            }
        }

        if (self::isPolylangActive() && function_exists('pll_get_post_translations')) {
            /** @phpstan-ignore-next-line */
            $translations = pll_get_post_translations($postId);
            if (is_array($translations)) {
                return array_map('intval', $translations);
            }
        }

        return [];
    }
}
