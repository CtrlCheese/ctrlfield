<?php

declare(strict_types=1);

namespace FieldForge\Integrations\Timber;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Data\FieldDataService;

/**
 * Registers FieldForge helper functions in the Timber/Twig environment.
 *
 * Zero cost when Timber is not installed — boot() returns immediately
 * if \Timber\Timber is not present.
 *
 * Available Twig functions:
 *   {{ ff('key') }}                    → fieldforge_get($key, $postId)
 *   {{ ff_all() }}                     → fieldforge_get_all($postId)
 *   {{ ff_user('key', userId) }}       → fieldforge_get_user($key, $userId)
 *   {% for row in ff_repeater('key') %} → iterates repeater rows
 *
 * Excluded from PHPStan — references Twig and Timber classes.
 */
final class TimberServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! class_exists('\Timber\Timber')) {
            return;
        }

        if (! function_exists('add_filter')) {
            return;
        }

        add_filter('timber/twig', [$this, 'addFunctions']);
    }

    public function addFunctions(\Twig\Environment $twig): \Twig\Environment
    {
        $twig->addFunction(new \Twig\TwigFunction(
            'ff',
            static function (string $key, ?int $postId = null): mixed {
                return FieldDataService::getInstance()->get(
                    $key,
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ff_all',
            static function (?int $postId = null): array {
                return FieldDataService::getInstance()->getAll(
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ff_user',
            static function (string $key, int $userId = 0): mixed {
                return FieldDataService::getInstance()->get(
                    $key,
                    $userId > 0 ? $userId : (function_exists('get_current_user_id') ? get_current_user_id() : 0),
                    'user',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ff_term',
            static function (string $key, int $termId): mixed {
                return FieldDataService::getInstance()->get($key, $termId, 'term');
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ff_repeater',
            static function (string $key, ?int $postId = null): array {
                $value = FieldDataService::getInstance()->get(
                    $key,
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
                return is_array($value) ? $value : [];
            },
        ));

        return $twig;
    }
}
