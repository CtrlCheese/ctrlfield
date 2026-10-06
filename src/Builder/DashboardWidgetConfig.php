<?php

declare(strict_types=1);

namespace CtrlField\Builder;

/**
 * Immutable configuration for a FieldGroup registered as a WP Dashboard widget.
 */
final class DashboardWidgetConfig
{
    /**
     * @param string $title    Widget title shown in the dashboard.
     * @param string $context  'normal' | 'side' | 'column3'
     * @param string $priority 'high' | 'default' | 'low'
     */
    public function __construct(
        public readonly string $title,
        public readonly string $context  = 'normal',
        public readonly string $priority = 'default',
    ) {}
}
