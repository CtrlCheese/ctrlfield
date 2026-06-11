<?php

declare(strict_types=1);

namespace FieldForge\Integrations\CLI;

use FieldForge\Bootstrap\ServiceProvider;
use FieldForge\Integrations\CLI\Commands\ExportCommand;
use FieldForge\Integrations\CLI\Commands\ExportCsvCommand;
use FieldForge\Integrations\CLI\Commands\ImportCommand;
use FieldForge\Integrations\CLI\Commands\ImportCsvCommand;
use FieldForge\Integrations\CLI\Commands\MigrateCommand;
use FieldForge\Integrations\CLI\Commands\ScaffoldCommand;
use FieldForge\Integrations\CLI\Commands\ValidateCommand;

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

        \WP_CLI::add_command('fieldforge migrate',     MigrateCommand::class);
        \WP_CLI::add_command('fieldforge export',     ExportCommand::class);
        \WP_CLI::add_command('fieldforge import',     ImportCommand::class);
        \WP_CLI::add_command('fieldforge validate',   ValidateCommand::class);
        \WP_CLI::add_command('fieldforge scaffold',   ScaffoldCommand::class);
        \WP_CLI::add_command('fieldforge export-csv', ExportCsvCommand::class);
        \WP_CLI::add_command('fieldforge import-csv', ImportCsvCommand::class);
    }
}
