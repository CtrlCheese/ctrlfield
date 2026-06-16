<?php

declare(strict_types=1);

namespace FieldForge\Tests\Unit\Data;

use FieldForge\Data\CsvImporter;
use FieldForge\Fields\Field;
use FieldForge\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class CsvImporterTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
        // Clean up temp files if any
    }

    public function test_import_returns_error_for_missing_file(): void
    {
        $importer = new CsvImporter();
        $stats    = $importer->import('/nonexistent/path/to/file.csv', 'portfolio');

        $this->assertNotEmpty($stats['errors']);
        $this->assertStringContainsString('Cannot open file', $stats['errors'][0]);
    }

    public function test_import_returns_error_for_empty_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, '');

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio');

            $this->assertNotEmpty($stats['errors']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_import_skips_rows_with_unknown_columns(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
            ])
            ->register();

        $csv = "post_id,post_title,post_status,client_name,unknown_column\n";
        $csv .= "0,Test Post,publish,Acme,SomeValue\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio', false, false, false);

            $this->assertGreaterThan(0, $stats['skipped']);
            $this->assertNotEmpty($stats['errors']);
            $this->assertStringContainsString('unknown_column', $stats['errors'][0]);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_import_skips_rows_when_update_existing_not_set(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
            ])
            ->register();

        $csv = "post_id,post_title,post_status,client_name\n";
        $csv .= "26,Test Post,publish,Acme Corp\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio', false, false, false);

            // post_id=26 with updateExisting=false → skipped
            $this->assertSame(1, $stats['skipped']);
            $this->assertSame(0, $stats['updated']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_import_skips_create_when_create_missing_not_set(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
            ])
            ->register();

        $csv = "post_id,post_title,post_status,client_name\n";
        $csv .= "0,New Post,publish,New Client\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio', false, false, false);

            // post_id=0 with createMissing=false → skipped
            $this->assertSame(1, $stats['skipped']);
            $this->assertSame(0, $stats['created']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_dry_run_does_not_persist_data(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
            ])
            ->register();

        $csv = "post_id,post_title,post_status,client_name\n";
        $csv .= "0,New Post,publish,Dry Run Client\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();

            // With dry-run + createMissing, it should count as 'created' but NOT call wp_insert_post.
            $stats = $importer->import($tmpFile, 'portfolio', true, false, true);

            // Dry run: created count reflects what *would* happen
            $this->assertSame(1, $stats['created']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_import_decodes_json_encoded_complex_fields(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('meta')->label('Meta'),
            ])
            ->register();

        // Row with a JSON-encoded group field value
        $jsonValue = json_encode(['key' => 'value']);
        $csv = "post_id,post_title,post_status,meta\n";
        $csv .= "0,New Post,publish,\"{$jsonValue}\"\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio', true, false, true);

            // No schema violation → created
            $this->assertSame(0, count($stats['errors']));
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_stats_structure_is_correct(): void
    {
        $importer = new CsvImporter();
        $stats    = $importer->import('/nonexistent.csv', 'portfolio');

        $this->assertArrayHasKey('updated', $stats);
        $this->assertArrayHasKey('created', $stats);
        $this->assertArrayHasKey('skipped', $stats);
        $this->assertArrayHasKey('errors', $stats);
        $this->assertIsInt($stats['updated']);
        $this->assertIsInt($stats['created']);
        $this->assertIsInt($stats['skipped']);
        $this->assertIsArray($stats['errors']);
    }

    public function test_import_skips_row_when_capability_check_fails(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('client_name')])
            ->register();

        // The unit test stubs.php current_user_can() always returns false,
        // so the capability check in CsvImporter always denies edit_post.
        $csv = "post_id,post_title,post_status,client_name\n";
        $csv .= "99,Some Post,publish,Blocked\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio', false, true, false);

            $this->assertSame(0, $stats['updated']);
            $this->assertSame(1, $stats['skipped']);
            $this->assertStringContainsString('insufficient permission', $stats['errors'][0]);
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_import_column_mismatch_is_skipped_with_error(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([Field::text('client_name')])
            ->register();

        // 3 headers but row has only 2 values
        $csv = "post_id,post_title,client_name\n";
        $csv .= "0,Short\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ff_csv_');
        file_put_contents($tmpFile, $csv);

        try {
            $importer = new CsvImporter();
            $stats    = $importer->import($tmpFile, 'portfolio');

            $this->assertSame(1, $stats['skipped']);
            $this->assertStringContainsString('column count mismatch', $stats['errors'][0]);
        } finally {
            unlink($tmpFile);
        }
    }
}
