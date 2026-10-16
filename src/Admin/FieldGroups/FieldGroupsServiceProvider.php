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
        add_action('admin_post_ctrlfield_save_field_group_php', [$page, 'handleSavePhp']);
        add_action('admin_init', [$this, 'protectJsonFolder']);
        add_filter('site_status_tests', [$this, 'siteHealthTests']);
    }

    /**
     * Protects a folder made by an older version or copied by hand: plain
     * .json files are rewritten as protected .json.php files, and the
     * server files are added.
     */
    public function protectJsonFolder(): void
    {
        $repository = new FieldGroupRepository();
        $dir        = $repository->directory();
        if (! is_dir($dir)) {
            return;
        }
        if ($repository->upgradeFiles() !== [] && function_exists('delete_transient')) {
            delete_transient(JsonFolderGuard::TRANSIENT);
        }
        if (! JsonFolderGuard::isProtected($dir)) {
            JsonFolderGuard::protect($dir);
        }
    }

    /**
     * @param  array<string, mixed> $tests
     * @return array<string, mixed>
     */
    public function siteHealthTests(array $tests): array
    {
        $tests['direct']['ctrlfield_json_private'] = [
            'label' => __('CtrlField field group files are private', 'ctrlfield'),
            'test'  => [$this, 'siteHealthJson'],
        ];

        return $tests;
    }

    /** @return array<string, mixed> */
    public function siteHealthJson(): array
    {
        $probe = JsonFolderGuard::probe((new FieldGroupRepository())->directory());
        $base  = [
            'test'  => 'ctrlfield_json_private',
            'badge' => ['label' => __('Security', 'ctrlfield'), 'color' => 'blue'],
        ];
        if ($probe['status'] !== JsonFolderGuard::EXPOSED) {
            return $base + [
                'status'      => 'good',
                'label'       => __('CtrlField field group files are private', 'ctrlfield'),
                'description' => '<p>' . esc_html__('The JSON files of the field groups made in the admin cannot be downloaded from the web.', 'ctrlfield') . '</p>',
            ];
        }

        return array_merge($base, [
            'status'      => 'critical',
            'badge'       => ['label' => __('Security', 'ctrlfield'), 'color' => 'red'],
            'label'       => __('CtrlField field group files can be downloaded', 'ctrlfield'),
            'description' => '<p>' . esc_html__('Anyone can download the files of your field groups and read every field name, instruction and choice. Your content and the database are not exposed.', 'ctrlfield') . '</p>',
            'actions'     => '<p>' . esc_html__('CtrlField protects them by itself when it can write to the folder: make it writable and open the admin again. Otherwise, on nginx, add this rule to the server block:', 'ctrlfield') . '</p><pre><code>'
                . esc_html(JsonFolderGuard::nginxRule($probe['url'])) . '</code></pre>'
                . '<p>' . esc_html__('Or move the folder outside the web root with the CTRLFIELD_JSON_PATH constant in wp-config.php.', 'ctrlfield') . '</p>',
        ]);
    }

    public function registerJsonGroups(): void
    {
        // Library groups first: groups that clone them find them registered.
        $groups = (new FieldGroupRepository())->all();
        uasort($groups, static fn (array $a, array $b): int => (int) ! ($a['library'] ?? false) <=> (int) ! ($b['library'] ?? false));

        foreach ($groups as $key => $group) {
            if (! $group['active']) {
                continue;
            }

            $label = 'ctrlfield-json/' . $key . FieldGroupRepository::EXTENSION;

            if ($group['_errors'] !== []) {
                SchemaErrors::add($label, new \RuntimeException(implode(' ', $group['_errors'])));
                continue;
            }

            try {
                if (JsonGroup::register($group, $skipped)) {
                    self::$registered[] = $key;
                    // Fields left out (a Clone that cannot work): the group shows without them.
                    foreach ($skipped as $message) {
                        SchemaErrors::add($label, new \RuntimeException($message . ' ' . __('The rest of the group is shown.', 'ctrlfield')));
                    }
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
