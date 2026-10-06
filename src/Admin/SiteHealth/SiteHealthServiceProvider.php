<?php

declare(strict_types=1);

namespace CtrlField\Admin\SiteHealth;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Registry\FieldRegistry;

/**
 * Registers CtrlField data in WordPress Site Health → Info panel.
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

        $proStatus = defined('CTRLFIELD_PRO_VERSION')
            ? 'Active (v' . CTRLFIELD_PRO_VERSION . ')'
            : 'Not installed';

        $info['ctrlfield'] = [
            'label'  => 'CtrlField',
            'fields' => [
                'version'     => ['label' => 'Version',                    'value' => defined('CTRLFIELD_VERSION') ? CTRLFIELD_VERSION : 'unknown'],
                'php_min'     => ['label' => 'PHP Requirement',            'value' => '8.2+'],
                'wp_min'      => ['label' => 'WP Requirement',             'value' => '6.4+'],
                'group_count' => ['label' => 'Registered field groups',    'value' => count($groups)],
                'field_count' => ['label' => 'Total fields registered',    'value' => $fieldCount],
                'pro_status'  => ['label' => 'CtrlField Pro',             'value' => $proStatus],
            ],
        ];

        return $info;
    }
}
