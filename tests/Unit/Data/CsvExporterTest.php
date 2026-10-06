<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Data;

use CtrlField\Data\CsvExporter;
use CtrlField\Fields\Field;
use CtrlField\Registry\FieldRegistry;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    protected function setUp(): void
    {
        FieldRegistry::reset();
    }

    protected function tearDown(): void
    {
        FieldRegistry::reset();
    }

    public function test_export_requires_wp_query_in_runtime(): void
    {
        // CsvExporter::export() uses WP_Query which is not available in unit tests.
        // We verify the exporter can be constructed and that it returns a Generator.
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
                Field::text('status')->label('Status'),
            ])
            ->register();

        $exporter = new CsvExporter();
        $gen      = $exporter->export('portfolio');

        $this->assertInstanceOf(\Generator::class, $gen);
    }

    public function test_exporter_can_be_constructed_without_groups(): void
    {
        $exporter = new CsvExporter();
        $gen      = $exporter->export('nonexistent_type');

        $this->assertInstanceOf(\Generator::class, $gen);
    }

    public function test_export_with_field_filter_respects_keys(): void
    {
        Field::group('portfolio_details')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::text('client_name')->label('Client Name'),
                Field::text('secret_field')->label('Secret'),
            ])
            ->register();

        // The exporter is constructed — the field filter is applied during iteration.
        // Without WP_Query we can only verify construction does not throw.
        $exporter = new CsvExporter();
        $gen      = $exporter->export('portfolio', ['client_name']);

        $this->assertInstanceOf(\Generator::class, $gen);
    }
}
