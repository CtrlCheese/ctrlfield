<?php

declare(strict_types=1);

namespace CtrlField\Integrations\CLI;

use CtrlField\Bootstrap\ServiceProvider;
use CtrlField\Integrations\CLI\Commands\ExportCommand;
use CtrlField\Integrations\CLI\Commands\ExportCsvCommand;
use CtrlField\Integrations\CLI\Commands\ImportCommand;
use CtrlField\Integrations\CLI\Commands\ImportCsvCommand;
use CtrlField\Integrations\CLI\Commands\MigrateCommand;
use CtrlField\Integrations\CLI\Commands\ScaffoldCommand;
use CtrlField\Integrations\CLI\Commands\ValidateCommand;

/**
 * Excluded from PHPStan — references WP_CLI which is not available outside CLI runtime.
 */
class CliServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! defined('WP_CLI') || ! \WP_CLI) {
            return;
        }

        \WP_CLI::add_command('ctrlfield migrate',     MigrateCommand::class);
        \WP_CLI::add_command('ctrlfield export',     ExportCommand::class);
        \WP_CLI::add_command('ctrlfield import',     ImportCommand::class);
        \WP_CLI::add_command('ctrlfield validate',   ValidateCommand::class);
        \WP_CLI::add_command('ctrlfield scaffold',   ScaffoldCommand::class);
        \WP_CLI::add_command('ctrlfield export-csv', ExportCsvCommand::class);
        \WP_CLI::add_command('ctrlfield import-csv', ImportCsvCommand::class);
    }
}
