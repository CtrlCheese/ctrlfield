<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Timber;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Data\FieldDataService;

/**
 * Registers CtrlField helper functions in the Timber/Twig environment.
 *
 * Zero cost when Timber is not installed — boot() returns immediately
 * if \Timber\Timber is not present.
 *
 * Available Twig functions:
 *   {{ ctrlf('key') }}                    → ctrlfield_get($key, $postId)
 *   {{ ctrlf_all() }}                     → ctrlfield_get_all($postId)
 *   {{ ctrlf_user('key', userId) }}       → ctrlfield_get_user($key, $userId)
 *   {% for row in ctrlf_repeater('key') %} → iterates repeater rows
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
            'ctrlf',
            static function (string $key, ?int $postId = null): mixed {
                return FieldDataService::getInstance()->get(
                    $key,
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_all',
            static function (?int $postId = null): array {
                return FieldDataService::getInstance()->getAll(
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_user',
            static function (string $key, int $userId = 0): mixed {
                return FieldDataService::getInstance()->get(
                    $key,
                    $userId > 0 ? $userId : (function_exists('get_current_user_id') ? get_current_user_id() : 0),
                    'user',
                );
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_term',
            static function (string $key, int $termId): mixed {
                return FieldDataService::getInstance()->get($key, $termId, 'term');
            },
        ));

        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_repeater',
            static function (string $key, ?int $postId = null): array {
                $value = FieldDataService::getInstance()->get(
                    $key,
                    $postId ?? (function_exists('get_the_ID') ? (int) get_the_ID() : 0),
                    'post',
                );
                return is_array($value) ? $value : [];
            },
        ));

        // ctrlf_component(section) — renders a flexible-content section array.
        // Equivalent to @ctrlfSection in Blade.
        // Usage: {{ ctrlf_component(section)|raw }}
        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_component',
            static function (array $section): string {
                return \CtrlField\Components\ComponentRenderer::render($section);
            },
            ['is_safe' => ['html']],
        ));

        // ctrlf_render('name', data) — renders a named component with explicit data.
        // Equivalent to @ctrlfComponent in Blade.
        // Usage: {{ ctrlf_render('hero', {'headline': 'Welcome'})|raw }}
        $twig->addFunction(new \Twig\TwigFunction(
            'ctrlf_render',
            static function (string $name, array $data = []): string {
                return \CtrlField\Components\ComponentRenderer::renderByName($name, $data);
            },
            ['is_safe' => ['html']],
        ));

        return $twig;
    }
}
