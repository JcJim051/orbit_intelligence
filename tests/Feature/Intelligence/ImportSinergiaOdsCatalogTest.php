<?php

namespace Tests\Feature\Intelligence;

use App\Models\OdsIndicator;
use App\Services\Ods\ImportSinergiaOdsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportSinergiaOdsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_catalogo_desde_tabla_js_de_sinergia(): void
    {
        Http::fake([
            ImportSinergiaOdsCatalog::SOURCE_URL => Http::response(<<<'JS'
var indicadoresData = [
    {
        "Objetivo": "3",
        "Indicador": "3.8.1.C",
        "Titulo": "Cobertura de servicios esenciales de salud",
        "Definicion": "Mide la cobertura de servicios esenciales de salud.",
        "Fuente": "DANE - Fuente de prueba",
        "csvUrl": "https://colaboracion.dnp.gov.co/ods/csv/3.8.1.C.csv",
        "excelUrl": "https://colaboracion.dnp.gov.co/ods/xls/3.8.1.C.xlsx"
    },
    {
        "Objetivo": "4",
        "Indicador": "4.1.1.P",
        "Titulo": "Proporción de niños con competencias mínimas",
        "Definicion": "Mide competencias mínimas.",
        "Fuente": "MEN",
        "csvUrl": "https://colaboracion.dnp.gov.co/ods/csv/4.1.1.P.csv",
        "excelUrl": "https://colaboracion.dnp.gov.co/ods/xls/4.1.1.P.xlsx"
    }
];
JS, 200),
        ]);

        $result = app(ImportSinergiaOdsCatalog::class)->import();

        $this->assertSame(['goals' => 2, 'targets' => 2, 'indicators' => 2], $result);
        $this->assertDatabaseHas('ods_goals', ['code' => '3', 'name' => 'Salud y bienestar']);
        $this->assertDatabaseHas('ods_targets', ['code' => '3.8', 'name' => 'Meta ODS 3.8']);
        $this->assertDatabaseHas('ods_indicators', [
            'code' => '3.8.1.C',
            'name' => 'Cobertura de servicios esenciales de salud',
            'source' => 'DANE - Fuente de prueba',
            'csv_url' => 'https://colaboracion.dnp.gov.co/ods/csv/3.8.1.C.csv',
            'excel_url' => 'https://colaboracion.dnp.gov.co/ods/xls/3.8.1.C.xlsx',
        ]);

        $this->assertNotNull(OdsIndicator::query()->where('code', '3.8.1.C')->value('imported_at'));
    }
}
