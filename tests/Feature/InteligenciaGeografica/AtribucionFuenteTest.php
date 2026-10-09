<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use App\Enums\ModoAnalisis;
use App\Enums\ServidoDesde;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class AtribucionFuenteTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
    }

    public function test_result_keeps_the_source_attribution_used_for_the_report(): void
    {
        $sincronizacion = CapaSincronizacion::factory()->create();
        $analisis = AnalisisArea::factory()->create(['modo' => ModoAnalisis::Oficial]);

        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $sincronizacion->capa_id,
            'capa_sincronizacion_id' => $sincronizacion->id,
            'cita_fuente' => 'Agencia Nacional de Minería. Títulos mineros. https://ejemplo.test/anm. Licencia de prueba.',
            'url_fuente' => 'https://ejemplo.test/anm',
            'licencia' => 'Licencia de prueba',
            'fecha_corte' => '2026-02-28 00:00:00',
            'consulted_at' => '2026-03-01 12:05:00',
            'servido_desde' => ServidoDesde::CopiaPorFallaFuente,
            'obsoleto' => true,
        ]);

        $resultado->refresh();

        $this->assertTrue($resultado->analisis->esOficial());
        $this->assertSame('Licencia de prueba', $resultado->licencia);
        $this->assertSame('https://ejemplo.test/anm', $resultado->url_fuente);
        $this->assertSame('2026-02-28 00:00:00', $resultado->fecha_corte->format('Y-m-d H:i:s'));
        $this->assertSame(ServidoDesde::CopiaPorFallaFuente, $resultado->servido_desde);
        $this->assertTrue($resultado->obsoleto);
        $this->assertTrue($resultado->sincronizacion->is($sincronizacion));
    }

    #[DataProvider('columnasDeAtribucion')]
    public function test_result_without_source_attribution_is_rejected(string $columna): void
    {
        $this->expectException(QueryException::class);

        AnalisisResultado::factory()->create([$columna => null]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function columnasDeAtribucion(): array
    {
        return [
            'cita' => ['cita_fuente'],
            'url' => ['url_fuente'],
            'licencia' => ['licencia'],
            'fecha de corte' => ['fecha_corte'],
            'momento de consulta' => ['consulted_at'],
            'origen del dato' => ['servido_desde'],
            'marca de desactualización' => ['obsoleto'],
            'sincronización usada' => ['capa_sincronizacion_id'],
        ];
    }

    public function test_result_cannot_cite_a_sync_from_another_layer(): void
    {
        $sincronizacion = CapaSincronizacion::factory()->create();

        $this->expectException(QueryException::class);

        AnalisisResultado::factory()->create([
            'capa_sincronizacion_id' => $sincronizacion->id,
        ]);
    }

    public function test_unchanged_source_date_cannot_record_a_download(): void
    {
        $sincronizacion = CapaSincronizacion::factory()->sinCambios()->create();

        $this->assertFalse($sincronizacion->cambio);
        $this->assertSame(EstadoSincronizacionCapa::SinCambios, $sincronizacion->estado);
        $this->assertSame(0, $sincronizacion->entidades_agregadas);

        $this->expectException(QueryException::class);

        CapaSincronizacion::factory()->create([
            'cambio' => false,
            'estado' => EstadoSincronizacionCapa::SinCambios,
            'entidades_agregadas' => 4,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
        ]);
    }
}
