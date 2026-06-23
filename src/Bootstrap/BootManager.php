<?php

declare(strict_types=1);

namespace FieldForge\Bootstrap;

use FieldForge\Registry\PendingCloneRegistry;
use FieldForge\Schema\SchemaLoader;
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
            throw new RuntimeException('FieldForge has not been booted. Ensure plugins_loaded has fired.');
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
            \FieldForge\Integrations\WordPress\WordPressServiceProvider::class,
            \FieldForge\Admin\MetaBox\MetaBoxServiceProvider::class,
            \FieldForge\Admin\Gutenberg\GutenbergServiceProvider::class,
            \FieldForge\Admin\UserMeta\UserMetaServiceProvider::class,
            \FieldForge\Admin\Comment\CommentMetaServiceProvider::class,
            \FieldForge\Admin\Taxonomy\TaxonomyMetaServiceProvider::class,
            \FieldForge\Admin\Columns\ColumnsServiceProvider::class,
            \FieldForge\Admin\QuickEdit\QuickEditServiceProvider::class,
            \FieldForge\Admin\Attachment\AttachmentModalServiceProvider::class,
            \FieldForge\Admin\NavMenu\NavMenuServiceProvider::class,
            \FieldForge\Admin\Dashboard\DashboardWidgetServiceProvider::class,
            \FieldForge\Admin\Inspector\InspectorServiceProvider::class,
            \FieldForge\Admin\SiteHealth\SiteHealthServiceProvider::class,
            \FieldForge\Integrations\Blade\ViewServiceProvider::class,
            \FieldForge\Integrations\REST\RestServiceProvider::class,
            \FieldForge\Integrations\CLI\CliServiceProvider::class,
            \FieldForge\Integrations\Timber\TimberServiceProvider::class,
            \FieldForge\Integrations\Translation\TranslationServiceProvider::class,
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
            $paths = defined('FIELDFORGE_SCHEMA_PATH') ? [(string) FIELDFORGE_SCHEMA_PATH] : [];

            // 2. Filter — themes/plugins add their own schema directories:
            //    add_filter('fieldforge/schema_paths', fn($p) => [...$p, get_template_directory() . '/fieldforge/']);
            /** @var string[] $paths */
            $paths = (array) apply_filters('fieldforge/schema_paths', $paths);

            foreach ($paths as $path) {
                if (is_string($path) && $path !== '') {
                    SchemaLoader::loadDirectory($path);
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
                                "FieldForge: Group '{$group->getKey()}' has an unresolved CloneField "
                                . "waiting for source '{$sourceKey}'. "
                                . 'Run `wp fieldforge validate` for details.'
                            );
                        }
                    }
                }
            }
        }, 6);
    }
}
