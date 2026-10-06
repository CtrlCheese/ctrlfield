<?php

declare(strict_types=1);

namespace CtrlField\Bootstrap;

use CtrlField\Registry\PendingCloneRegistry;
use CtrlField\Schema\Exceptions\SchemaDirectoryNotFoundException;
use CtrlField\Schema\SchemaLoader;
use RuntimeException;

class BootManager
{
    private static ?ServiceContainer $container = null;

    /** @var ServiceProvider[] */
    private static array $bootedProviders = [];

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$container = new ServiceContainer();
        self::$container->instance(ServiceContainer::class, self::$container);

        self::registerProviders();
        self::bootProviders();
        self::loadSchemaPath();

        self::$booted = true;
    }

    public static function container(): ServiceContainer
    {
        if (self::$container === null) {
            throw new RuntimeException('CtrlField has not been booted. Ensure plugins_loaded has fired.');
        }

        return self::$container;
    }

    public static function isBooted(): bool
    {
        return self::$booted;
    }

    /** @return class-string<ServiceProvider>[] */
    private static function providers(): array
    {
        return [
            \CtrlField\Integrations\WordPress\WordPressServiceProvider::class,
            \CtrlField\Admin\MetaBox\MetaBoxServiceProvider::class,
            \CtrlField\Admin\Gutenberg\GutenbergServiceProvider::class,
            \CtrlField\Admin\UserMeta\UserMetaServiceProvider::class,
            \CtrlField\Admin\Comment\CommentMetaServiceProvider::class,
            \CtrlField\Admin\Taxonomy\TaxonomyMetaServiceProvider::class,
            \CtrlField\Admin\Columns\ColumnsServiceProvider::class,
            \CtrlField\Admin\QuickEdit\QuickEditServiceProvider::class,
            \CtrlField\Admin\Attachment\AttachmentModalServiceProvider::class,
            \CtrlField\Admin\NavMenu\NavMenuServiceProvider::class,
            \CtrlField\Admin\Dashboard\DashboardWidgetServiceProvider::class,
            \CtrlField\Admin\Inspector\InspectorServiceProvider::class,
            \CtrlField\Admin\SiteHealth\SiteHealthServiceProvider::class,
            \CtrlField\Integrations\Blade\ViewServiceProvider::class,
            \CtrlField\Integrations\REST\RestServiceProvider::class,
            \CtrlField\Integrations\CLI\CliServiceProvider::class,
            \CtrlField\Integrations\Timber\TimberServiceProvider::class,
            \CtrlField\Integrations\Translation\TranslationServiceProvider::class,
        ];
    }

    private static function registerProviders(): void
    {
        foreach (self::providers() as $providerClass) {
            /** @var ServiceProvider $provider */
            $provider = new $providerClass(self::$container);
            $provider->register();
            self::$bootedProviders[] = $provider;
        }
    }

    private static function bootProviders(): void
    {
        foreach (self::$bootedProviders as $provider) {
            $provider->boot();
        }
    }

    private static function loadSchemaPath(): void
    {
        add_action('init', static function (): void {
            // 1. Constant-defined path (wp-config.php / wp-env config).
            $paths = defined('CTRLFIELD_SCHEMA_PATH') ? [(string) CTRLFIELD_SCHEMA_PATH] : [];

            // 2. Filter — themes/plugins add their own schema directories:
            //    add_filter('ctrlfield/schema_paths', fn($p) => [...$p, get_template_directory() . '/ctrlfield/']);
            /** @var string[] $paths */
            $paths = (array) apply_filters('ctrlfield/schema_paths', $paths);

            foreach ($paths as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                // A wrong path in wp-config must not take the whole site down:
                // skip it and tell admins instead.
                try {
                    SchemaLoader::loadDirectory($path);
                } catch (SchemaDirectoryNotFoundException $e) {
                    error_log($e->getMessage());
                    add_action('admin_notices', static function () use ($path): void {
                        if (! current_user_can('manage_options')) {
                            return;
                        }
                        printf(
                            '<div class="notice notice-error"><p>%s <code>%s</code></p></div>',
                            esc_html__('CtrlField: schema directory not found, its field groups were not loaded:', 'ctrlfield'),
                            esc_html($path)
                        );
                    });
                }
            }
        }, 5);

        // After schema files load, report any unresolved CloneField sources.
        add_action('init', static function (): void {
            if (PendingCloneRegistry::hasPending()) {
                foreach (PendingCloneRegistry::getPending() as $sourceKey => $groups) {
                    foreach ($groups as $group) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(
                                "CtrlField: Group '{$group->getKey()}' has an unresolved CloneField "
                                . "waiting for source '{$sourceKey}'. "
                                . 'Run `wp ctrlfield validate` for details.'
                            );
                        }
                    }
                }
            }
        }, 6);
    }
}
