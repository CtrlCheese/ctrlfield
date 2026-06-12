<?php

declare(strict_types=1);

namespace FieldForge\Admin\SiteHealth;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Registry\FieldRegistry;

/**
 * Registers FieldForge data in WordPress Site Health → Info panel.
 */
final class SiteHealthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        add_filter('debug_information', [$this, 'addDebugInfo']);
    }

    /** @param array<string, mixed> $info */
    public function addDebugInfo(array $info): array
    {
        $groups     = FieldRegistry::all();
        $fieldCount = 0;

        foreach ($groups as $g) {
            $fieldCount += count($g->getFields());
        }

        $proStatus = defined('FIELDFORGE_PRO_VERSION')
            ? 'Active (v' . FIELDFORGE_PRO_VERSION . ')'
            : 'Not installed';

        $info['fieldforge'] = [
            'label'  => 'FieldForge',
            'fields' => [
                'version'     => ['label' => 'Version',                    'value' => defined('FIELDFORGE_VERSION') ? FIELDFORGE_VERSION : 'unknown'],
                'php_min'     => ['label' => 'PHP Requirement',            'value' => '8.2+'],
                'wp_min'      => ['label' => 'WP Requirement',             'value' => '6.4+'],
                'group_count' => ['label' => 'Registered field groups',    'value' => count($groups)],
                'field_count' => ['label' => 'Total fields registered',    'value' => $fieldCount],
                'pro_status'  => ['label' => 'FieldForge Pro',             'value' => $proStatus],
            ],
        ];

        return $info;
    }
}
