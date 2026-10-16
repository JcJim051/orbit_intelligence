<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use App\Enums\UserRole;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaObjetoCache;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\Municipio;
use App\Models\User;
use App\Services\InteligenciaGeografica\InformePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class InformePdfTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    private const CUADRADO = 'MULTIPOLYGON(((4910000 1990000, 4920000 1990000, 4920000 2000000, 4910000 2000000, 4910000 1990000)))';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->withoutVite();
    }

    public function test_a_guest_is_redirected_and_a_member_cannot_download_the_report(): void
    {
        $analisis = AnalisisArea::factory()->create();

        $this->get(route('analisis.informe.pdf', $analisis))->assertRedirect(route('login'));

        $miembro = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($miembro)
            ->get(route('analisis.informe.pdf', $analisis))
            ->assertForbidden();
    }

    public function test_the_pdf_lists_the_analysis_the_layers_and_an_unavailable_source(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ana Gómez']);
        $analisis = $this->analisis($admin);
        $anm = $this->capaConCruce($analisis, 'ANM_TITULOS_SOLICITUDES', 'Títulos', [
            '_capa' => 'titulo',
            'CODIGO_EXPEDIENTE' => 'EXP-1',
            'MINERALES' => 'Oro',
            'NOMBRE_DE_TITULAR' => 'Titular de prueba',
            'ESTADO' => 'Vigente',
        ]);
        $this->capaConCruce($analisis, 'RUNAP_AREAS_PROTEGIDAS', 'RUNAP', [
            'ap_nombre' => 'Sierra de la Macarena',
            'ap_categoria' => 'Parque Nacional',
        ]);
        $this->capaConCruce($analisis, 'IGAC_CATASTRO_R1', 'Catastro', [
            'CODIGO' => '503130001',
            'codigo_municipio' => '50313',
        ]);
        $this->capaConCruce($analisis, 'CORMACARENA_POT_CLASIFICACION', 'POT', [
            'Clasificacion' => 'Rural',
        ]);
        AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => Capa::factory()->create(['codigo' => 'DANE_GRILLA_POBLACION_1KM', 'nombre' => 'Grilla'])->id,
            'conteo' => 0,
        ]);
        CapaSincronizacion::factory()->create([
            'capa_id' => Capa::factory()->create([
                'codigo' => 'SGC_AMENAZA_MOVIMIENTOS_MASA',
                'nombre' => 'Amenaza',
                'url_servicio' => 'https://ejemplo.test/sgc',
                'licencia' => 'Licencia SGC',
            ])->id,
            'estado' => EstadoSincronizacionCapa::FuenteNoDisponible,
            'mensaje_error' => 'La fuente no respondió.',
            'cambio' => false,
            'entidades_agregadas' => 0,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
        ]);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Gobernación del Meta', $html);
        $this->assertStringContainsString('Gerencia de Información y Estudios Económicos', $html);
        $this->assertStringContainsString('Ana Gómez', $html);
        $this->assertStringContainsString('50001 — Villavicencio', $html);
        $this->assertStringContainsString('10.000,00 ha', $html);
        $this->assertStringContainsString('Quedaron fuera del Meta', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('Títulos vigentes', $html);
        $this->assertStringContainsString('EXP-1', $html);
        $this->assertStringContainsString('Oro', $html);
        $this->assertStringContainsString('Titular de prueba', $html);
        $this->assertStringContainsString('Sierra de la Macarena', $html);
        $this->assertStringContainsString('Parque Nacional', $html);
        $this->assertStringContainsString('Rural', $html);
        $this->assertStringContainsString('El paquete abierto del IGAC no incluye avalúo catastral', $html);
        $this->assertStringContainsString('https://ejemplo.test/capa', $html);
        $this->assertStringContainsString('Licencia de prueba', $html);
        $this->assertStringContainsString('Copia local', $html);
        $this->assertStringContainsString('Fuente no disponible — sin datos sincronizados', $html);
        $this->assertStringContainsString('Motivo: La fuente no respondió.', $html);
        $this->assertStringContainsString('Último intento: 01/03/2026 12:04', $html);
        $this->assertStringNotContainsString('Sin entidades', $html);
        $this->assertDoesNotMatchRegularExpression('/SGC_AMENAZA_MOVIMIENTOS_MASA.{0,500}0 entidades/s', $html);
        $this->assertMatchesRegularExpression('/DANE_GRILLA_POBLACION_1KM.{0,200}0 entidades/s', $html);
        $this->assertStringContainsString('Títulos mineros: 1 entidades', $html);
        $this->assertStringContainsString('Amenaza: Sin información (fuente no disponible)', $html);
        $this->assertStringContainsString($anm->cita_fuente, $html);

        $respuesta = $this->actingAs($admin)->get(route('analisis.informe.pdf', $analisis));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('index', ['analisis' => $analisis->id]))
            ->assertOk()
            ->assertSee('Descargar informe PDF');

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('historial'))
            ->assertOk()
            ->assertSee('Descargar informe PDF');
    }

    public function test_the_pdf_sums_avaluo_only_when_the_package_field_is_present(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $capa = Capa::factory()->create(['codigo' => 'IGAC_CATASTRO_R1', 'nombre' => 'Catastro']);
        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $capa->id,
            'conteo' => 2,
        ]);
        $this->predio($capa->id, $resultado->version->numero, '503130001', 1000000);
        $this->predio($capa->id, $resultado->version->numero, '503130002', 3000000);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Avalúo (AVALUO): total 4.000.000,00', $html);
        $this->assertStringContainsString('Promedio por predio: 2.000.000,00', $html);
        $this->assertStringContainsString('Promedio por ha: 400,00', $html);
        $this->assertStringNotContainsString('El paquete abierto del IGAC no incluye avalúo catastral', $html);
    }

    public function test_overlapping_features_report_the_union_area_and_each_feature_area(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $resultado = $this->capaConCruce($analisis, 'CORMACARENA_ECOSISTEMAS', 'Ecosistemas', ['_capa' => 'e0']);
        $mitad = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['_capa' => 'e1'],
            'identificador_origen' => 'eco-2',
            'valid_from' => $resultado->version->numero,
        ]);
        DB::update(
            "update inteligencia.capa_objeto_cache set geom = ST_SetSRID(ST_GeomFromText('MULTIPOLYGON(((4910000 1990000, 4915000 1990000, 4915000 2000000, 4910000 2000000, 4910000 1990000)))'), 9377) where id = ?",
            [$mitad->id],
        );
        $resultado->update(['conteo' => 2]);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringNotContainsString('15.000,00', $html);
        $this->assertMatchesRegularExpression('/CORMACARENA_ECOSISTEMAS.{0,250}10\.000,00 ha \(100,0 %\)/s', $html);
        $this->assertStringContainsString('5.000,00', $html);
    }

    public function test_a_layer_without_a_successful_sync_is_not_reported_as_empty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        CapaSincronizacion::factory()->create([
            'capa_id' => Capa::factory()->create([
                'codigo' => 'IDEAM_COBERTURA_TIERRA_2024',
                'nombre' => 'Cobertura',
            ])->id,
            'estado' => EstadoSincronizacionCapa::Fallido,
            'mensaje_error' => 'Tiempo de espera agotado.',
            'cambio' => false,
            'entidades_agregadas' => 0,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
        ]);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Fuente no disponible — sin datos sincronizados', $html);
        $this->assertStringContainsString('Motivo: Tiempo de espera agotado.', $html);
        $this->assertStringNotContainsString('Sin entidades', $html);
        $this->assertStringNotContainsString('Sin cruce', $html);
    }

    public function test_an_unavailable_alert_is_not_called_a_miss(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        Capa::factory()->create(['codigo' => 'SGC_AMENAZA_MOVIMIENTOS_MASA', 'nombre' => 'Amenaza']);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Amenaza: Sin información (fuente no disponible)', $html);
        $this->assertStringNotContainsString('Amenaza: Sin cruce', $html);
    }

    public function test_municipalities_come_from_dane_when_the_local_catalog_has_no_geometry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'geoportal.dane.gov.co/*' => Http::response([
                'features' => [[
                    'attributes' => ['MPIO_CDPMP' => '50001', 'MPIO_CNMBRE' => 'VILLAVICENCIO'],
                ]],
            ]),
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin, false);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('50001 — VILLAVICENCIO', $html);
        $this->assertStringNotContainsString('Ningún municipio del catálogo intersecta', $html);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'FeatureServer/317/query'));
    }

    public function test_only_configured_soil_classes_count_as_a_restriction(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $resultado = $this->capaConCruce($analisis, 'CORMACARENA_POT_CLASIFICACION', 'POT', [
            'Clasificacion' => 'Protección',
        ]);
        $suburbano = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['Clasificacion' => 'Suburbano'],
            'identificador_origen' => 'pot-2',
            'valid_from' => $resultado->version->numero,
        ]);
        DB::update(
            "update inteligencia.capa_objeto_cache set geom = ST_SetSRID(ST_GeomFromText('MULTIPOLYGON(((4910000 1990000, 4915000 1990000, 4915000 2000000, 4910000 2000000, 4910000 1990000)))'), 9377) where id = ?",
            [$suburbano->id],
        );

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Protección', $html);
        $this->assertStringContainsString('Suburbano', $html);
        $this->assertStringContainsString('Restricción de suelo: Protección: 10.000,00 ha (100,0 %)', $html);
        $this->assertDoesNotMatchRegularExpression('/Restricción de suelo:.*Suburbano/s', $html);

        config(['inteligencia.restricciones_suelo' => ['suburbano']]);
        $ajustado = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('Restricción de suelo: Suburbano: 5.000,00 ha (50,0 %)', $ajustado);
        $this->assertStringNotContainsString('Restricción de suelo: Protección:', $ajustado);
    }

    public function test_the_service_url_replaces_the_pending_placeholder(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $resultado = $this->capaConCruce($analisis, 'RUNAP_AREAS_PROTEGIDAS', 'RUNAP', [
            'ap_nombre' => 'Sierra de la Macarena',
            'ap_categoria' => 'Parque Nacional',
        ]);
        $resultado->capa()->update(['url_servicio' => 'https://servicios.ejemplo.test/runap']);
        $resultado->update([
            'url_fuente' => 'URL pendiente de confirmación',
            'cita_fuente' => 'Parques Nacionales. URL pendiente de confirmación. Licencia de prueba.',
        ]);

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('https://servicios.ejemplo.test/runap', $html);
        $this->assertStringNotContainsString('URL pendiente de confirmación', $html);
    }

    private function analisis(User $usuario, bool $conLimite = true): AnalisisArea
    {
        if ($conLimite) {
            $municipio = Municipio::factory()->create([
                'codigo_dane' => '50001',
                'nombre' => 'Villavicencio',
            ]);
            DB::insert(
                'insert into suelos.limite_municipio (municipio_id, geom) values (?, ST_SetSRID(ST_GeomFromText(?), 9377))',
                [$municipio->id, self::CUADRADO],
            );
        }
        $analisis = AnalisisArea::factory()->create([
            'user_id' => $usuario->id,
            'fecha' => '2026-03-01 12:00:00',
        ]);
        DB::update(
            'update inteligencia.analisis_area set ha_fuera_meta = 12.5, pct_fuera_meta = 10 where id = ?',
            [$analisis->id],
        );

        return $analisis->fresh();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function capaConCruce(AnalisisArea $analisis, string $codigo, string $nombre, array $atributos): AnalisisResultado
    {
        $capa = Capa::factory()->create(['codigo' => $codigo, 'nombre' => $nombre]);
        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $capa->id,
            'conteo' => 1,
            'area_m2' => 10000,
            'cita_fuente' => 'Entidad de prueba. '.$nombre.'. https://ejemplo.test/capa. Licencia de prueba.',
            'url_fuente' => 'https://ejemplo.test/capa',
            'licencia' => 'Licencia de prueba',
        ]);
        $cache = CapaObjetoCache::factory()->create([
            'capa_id' => $capa->id,
            'atributos' => $atributos,
            'identificador_origen' => $codigo.'-1',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->cuadrado($cache->id);

        return $resultado->fresh('version');
    }

    private function predio(int $capaId, int $version, string $codigo, int $avaluo): void
    {
        $cache = CapaObjetoCache::factory()->create([
            'capa_id' => $capaId,
            'atributos' => ['CODIGO' => $codigo, 'AVALUO' => $avaluo],
            'identificador_origen' => $codigo,
            'valid_from' => $version,
        ]);
        $this->cuadrado($cache->id);
    }

    private function cuadrado(int $cacheId): void
    {
        DB::update(
            'update inteligencia.capa_objeto_cache set geom = ST_SetSRID(ST_GeomFromText(?), 9377) where id = ?',
            [self::CUADRADO, $cacheId],
        );
    }
}
