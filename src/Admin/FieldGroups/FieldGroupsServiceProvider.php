<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Schema\JsonGroup;
use CtrlField\Schema\SchemaErrors;

/**
 * Field groups created in CtrlField → Field Groups (stored as JSON).
 *
 * Registered on init:15, after schema files (init:5) and theme code (init:10),
 * so code always wins: a JSON group whose key is already registered by code
 * is skipped and flagged on the Field Groups page.
 */
final class FieldGroupsServiceProvider extends ServiceProvider
{
    /** @var list<string> keys registered from JSON this request */
    private static array $registered = [];

    /** @var list<string> JSON keys skipped because code defines them */
    private static array $shadowed = [];

    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('init', [$this, 'registerJsonGroups'], 15);

        $page = new FieldGroupsPage(new FieldGroupRepository());
        add_action('admin_menu', [$page, 'registerMenu'], 10);
        add_action('admin_enqueue_scripts', [$page, 'enqueueAssets']);
        add_action('admin_post_ctrlfield_save_field_group', [$page, 'handleSave']);
        add_action('admin_post_ctrlfield_delete_field_group', [$page, 'handleDelete']);
    }

    public function registerJsonGroups(): void
    {
        foreach ((new FieldGroupRepository())->all() as $key => $group) {
            if (! $group['active']) {
                continue;
            }

            $label = 'ctrlfield-json/' . $key . '.json';

            if ($group['_errors'] !== []) {
                SchemaErrors::add($label, new \RuntimeException(implode(' ', $group['_errors'])));
                continue;
            }

            try {
                if (JsonGroup::register($group)) {
                    self::$registered[] = $key;
                } else {
                    self::$shadowed[] = $key;
                }
            } catch (\Throwable $e) {
                SchemaErrors::add($label, $e);
            }
        }
    }

    /** @return list<string> */
    public static function registered(): array
    {
        return self::$registered;
    }

    /** @return list<string> */
    public static function shadowed(): array
    {
        return self::$shadowed;
    }
}
