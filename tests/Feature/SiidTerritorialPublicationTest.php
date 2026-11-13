<?php

namespace Tests\Feature;

use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\SiidIndicador;
use App\Models\SiidPublicacion;
use App\Services\Siid\TerritorialPublicSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiidTerritorialPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_series_only_contains_approved_data_and_prioritizes_meta_total(): void
    {
        $indicator = IndicadorTerritorial::query()->create([
            'codigo' => 'IT-PUBLICO-001',
            'nombre' => 'Indicador territorial público',
            'unidad_medida' => 'Número',
            'periodicidad' => 'anual',
            'estado' => 'activo',
            'activo' => true,
        ]);

        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2023',
            'periodo_inicio' => '2023-01-01',
            'periodo_fin' => '2023-12-31',
            'fecha_corte' => '2024-03-31',
            'valor' => 12.5,
            'estado' => IndicadorTerritorialDato::ESTADO_APROBADO,
            'territorio_codigo' => '50',
            'desagregacion_tipo' => 'Total',
            'desagregacion_valor' => 'Total',
            'observacion' => 'Observación interna reservada',
            'enlace_evidencia' => 'https://example.test/evidencia-privada',
        ]);
        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2024',
            'valor' => 20,
            'estado' => IndicadorTerritorialDato::ESTADO_PENDIENTE,
            'territorio_codigo' => '50',
            'desagregacion_tipo' => 'Total',
            'desagregacion_valor' => 'Total',
            'observacion' => 'Este valor no puede publicarse',
        ]);
        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2022',
            'valor' => 8,
            'estado' => IndicadorTerritorialDato::ESTADO_APROBADO,
            'territorio_codigo' => '50001',
            'desagregacion_tipo' => 'Sexo',
            'desagregacion_valor' => 'Mujeres',
        ]);

        $series = app(TerritorialPublicSeries::class)->build($indicator);
        $serialized = json_encode($series);

        $this->assertCount(2, $series);
        $this->assertTrue($series[0]['predeterminada']);
        $this->assertSame('Meta', $series[0]['territorio']);
        $this->assertCount(1, $series[0]['puntos']);
        $this->assertSame('2023', $series[0]['puntos'][0]['periodo']);
        $this->assertStringNotContainsString('Observación interna reservada', $serialized);
        $this->assertStringNotContainsString('evidencia-privada', $serialized);
        $this->assertStringNotContainsString('"periodo":"2024"', $serialized);
    }

    public function test_public_territorial_view_renders_chart_and_table_without_internal_fields(): void
    {
        $indicator = new IndicadorTerritorial([
            'codigo' => 'IT-PUBLICO-002',
            'nombre' => 'Indicador de prueba',
            'unidad_medida' => 'Porcentaje',
        ]);
        $series = [[
            'key' => hash('sha256', '50|Total|Total'),
            'territorio_codigo' => '50',
            'territorio' => 'Meta',
            'desagregacion_tipo' => 'Total',
            'desagregacion_valor' => 'Total',
            'predeterminada' => true,
            'puntos' => [[
                'periodo' => '2025',
                'periodo_inicio' => '2025-01-01',
                'periodo_fin' => '2025-12-31',
                'fecha_corte' => '2026-03-31',
                'valor' => 76.4,
                'preliminar' => false,
            ]],
        ]];

        $html = view('micrositio.siid.territorial-series', [
            'indicador' => $indicator,
            'territorialSeries' => $series,
        ])->render();

        $this->assertStringContainsString('Evolución del indicador', $html);
        $this->assertStringContainsString('data-territorial-chart', $html);
        $this->assertStringContainsString('Fecha de corte', $html);
        $this->assertStringNotContainsString('observacion', $html);
        $this->assertStringNotContainsString('enlace_evidencia', $html);
    }

    public function test_publication_search_matches_code_and_name_for_both_indicator_types(): void
    {
        DB::statement("ATTACH DATABASE ':memory:' AS siid_publicacion");
        DB::statement('CREATE TABLE siid_publicacion.indicadores_siid (id INTEGER PRIMARY KEY AUTOINCREMENT, codigo VARCHAR NOT NULL, nombre TEXT NOT NULL, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE siid_publicacion.publicaciones (id INTEGER PRIMARY KEY AUTOINCREMENT, publicable_type VARCHAR NOT NULL, publicable_id INTEGER NOT NULL, visible BOOLEAN DEFAULT 0, etiqueta VARCHAR, created_at DATETIME, updated_at DATETIME)');

        $territorial = IndicadorTerritorial::query()->create([
            'codigo' => 'IT-POB-001',
            'nombre' => 'Población territorial atendida',
            'unidad_medida' => 'Número',
            'periodicidad' => 'anual',
            'estado' => 'activo',
            'activo' => true,
        ]);

        $siid = SiidIndicador::query()->create([
            'codigo' => 'ID-POB-001',
            'nombre' => 'Población total',
        ]);

        $territorial->publicacion()->firstOrCreate([], ['visible' => false]);
        $siid->publicacion()->create(['visible' => false]);

        $this->assertCount(2, SiidPublicacion::query()->buscarIndicador('poblac')->get());
        $this->assertCount(1, SiidPublicacion::query()
            ->where('publicable_type', SiidIndicador::class)
            ->buscarIndicador('id-pob')
            ->get());
        $this->assertCount(1, SiidPublicacion::query()
            ->where('publicable_type', IndicadorTerritorial::class)
            ->buscarIndicador('territorial atendida')
            ->get());
    }
}
