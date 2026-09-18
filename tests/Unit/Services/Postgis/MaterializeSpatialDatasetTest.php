<?php

namespace Tests\Unit\Services\Postgis;

use App\Enums\DatasetFieldType;
use App\Models\DatasetFormField;
use App\Services\Postgis\MaterializeSpatialDataset;
use PHPUnit\Framework\TestCase;

class MaterializeSpatialDatasetTest extends TestCase
{
    public function test_it_maps_configurable_fields_to_postgresql_types(): void
    {
        $service = new MaterializeSpatialDataset;

        $this->assertSame('varchar(250)', $service->columnType(new DatasetFormField([
            'field_type' => DatasetFieldType::ShortText,
            'validation_rules' => ['max_length' => 250],
        ])));
        $this->assertSame('bigint', $service->columnType(new DatasetFormField(['field_type' => DatasetFieldType::Integer])));
        $this->assertSame('numeric', $service->columnType(new DatasetFormField(['field_type' => DatasetFieldType::Decimal])));
        $this->assertSame('jsonb', $service->columnType(new DatasetFormField(['field_type' => DatasetFieldType::MultiSelect])));
    }

    public function test_it_builds_a_postgresql_safe_physical_table_name(): void
    {
        $service = new MaterializeSpatialDataset;

        $this->assertSame('puntos_criticos', $service->tableName('puntos-criticos'));
        $this->assertLessThanOrEqual(63, mb_strlen($service->tableName(str_repeat('conjunto-', 15).'final')));
    }

    public function test_line_and_polygon_tables_use_compound_geometry_types(): void
    {
        $service = new MaterializeSpatialDataset;

        $this->assertSame('Point', $service->postgisGeometryType('point'));
        $this->assertSame('MultiLineString', $service->postgisGeometryType('line'));
        $this->assertSame('MultiPolygon', $service->postgisGeometryType('polygon'));
    }

    public function test_initial_import_discards_z_before_reprojection_to_a_2d_capture_column(): void
    {
        $service = new MaterializeSpatialDataset;

        $this->assertSame(
            'ST_Transform(ST_Force2D("geom"), 9377)',
            $service->initialImportGeometryExpression('"geom"', 9377, 'point'),
        );
        $this->assertSame(
            'ST_Multi(ST_Transform(ST_Force2D("geom"), 9377))',
            $service->initialImportGeometryExpression('"geom"', 9377, 'line'),
        );
        $this->assertSame(
            'ST_Multi(ST_Transform(ST_Force2D("geom"), 9377))',
            $service->initialImportGeometryExpression('"geom"', 9377, 'polygon'),
        );
    }
}
