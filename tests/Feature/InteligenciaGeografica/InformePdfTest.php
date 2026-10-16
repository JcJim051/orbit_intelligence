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
        $this->fingirTeselas();
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
        $this->assertStringContainsString('data:image/png;base64,', $html);
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
        $this->fingirTeselas();
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
        $this->fingirTeselas();
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
        $this->fingirTeselas();
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
        $this->fingirTeselas();
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
            'tile.openstreetmap.org/*' => Http::response($this->pngRojo(), 200, ['Content-Type' => 'image/png']),
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
        $this->fingirTeselas();
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
        $this->fingirTeselas();
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

    public function test_ecosystems_are_grouped_and_rows_that_round_to_zero_are_omitted(): void
    {
        $this->fingirTeselas();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $resultado = $this->capaConCruce($analisis, 'CORMACARENA_ECOSISTEMAS', 'Ecosistemas', ['_capa' => 'e5']);
        $otro = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['_capa' => 'e5'],
            'identificador_origen' => 'e5-2',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->cuadrado($otro->id);
        $diminuto = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['_capa' => 'e0'],
            'identificador_origen' => 'e0-1',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->geometria($diminuto->id, 'MULTIPOLYGON(((4910000 1990000, 4910006 1990000, 4910006 1990006, 4910000 1990006, 4910000 1990000)))');

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertMatchesRegularExpression('/Áreas protegidas SINAP.{0,120}2.{0,80}10\.000,00/s', $html);
        $this->assertStringNotContainsString('Humedales puntuales', $html);
        $this->assertStringNotContainsString('La tabla muestra las primeras entidades', $html);
    }

    public function test_drains_are_grouped_and_lines_are_reported_in_kilometres(): void
    {
        $this->fingirTeselas();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $resultado = $this->capaConCruce($analisis, 'META_DRENAJE', 'Drenajes', [
            'NOMBRE_GEO' => 'Caño Grande',
            '_capa' => 'sencillo',
        ]);
        $linea = CapaObjetoCache::query()->where('capa_id', $resultado->capa_id)->first();
        $this->geometria((int) $linea?->id, 'LINESTRING(4910000 1995000, 4920000 1995000)');
        $fuera = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['NOMBRE_GEO' => 'Caño Seco', '_capa' => 'sencillo'],
            'identificador_origen' => 'seco',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->geometria($fuera->id, 'LINESTRING(1000 1000, 2000 1000)');
        $doble = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['NOMBRE_GEO' => 'Río Ancho', '_capa' => 'doble'],
            'identificador_origen' => 'doble-1',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->cuadrado($doble->id);
        $diminuto = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['NOMBRE_GEO' => 'Quebrada', '_capa' => 'doble'],
            'identificador_origen' => 'doble-0',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->geometria($diminuto->id, 'MULTIPOLYGON(((4910000 1990000, 4910006 1990000, 4910006 1990006, 4910000 1990006, 4910000 1990000)))');

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertMatchesRegularExpression('/Caño Grande.{0,160}Drenaje sencillo.{0,80}10,00 km/s', $html);
        $this->assertMatchesRegularExpression('/Río Ancho.{0,160}Drenaje doble.{0,80}10\.000,00 ha/s', $html);
        $this->assertStringNotContainsString('Caño Seco', $html);
        $this->assertStringNotContainsString('Quebrada', $html);
        $this->assertStringNotContainsString('>0,00 ha<', $html);
        $this->assertStringNotContainsString('>0,00 km<', $html);
    }

    public function test_a_pot_without_the_municipality_says_the_layer_has_no_coverage(): void
    {
        $this->fingirTeselas();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        Municipio::query()->where('codigo_dane', '50001')->update([
            'codigo_dane' => '50223',
            'nombre' => 'Cubarral',
        ]);
        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => Capa::factory()->create([
                'codigo' => 'CORMACARENA_POT_CLASIFICACION',
                'nombre' => 'POT',
            ])->id,
            'conteo' => 0,
            'area_m2' => 0,
        ]);
        $cache = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
            'atributos' => ['Municipio' => 'Villavicencio', 'Clasificacion' => 'Rural'],
            'identificador_origen' => 'pot-villa',
            'valid_from' => $resultado->version->numero,
        ]);
        $this->geometria($cache->id, 'MULTIPOLYGON(((1000 1000, 2000 1000, 2000 2000, 1000 2000, 1000 1000)))');

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('La capa no tiene cobertura para este municipio', $html);
        $this->assertStringContainsString('Restricción de suelo: La capa no tiene cobertura para este municipio', $html);
        $this->assertStringNotContainsString('0 entidades', $html);
        $this->assertStringNotContainsString('Sin cruce', $html);

        $cache->update(['atributos' => ['Municipio' => 'Cubarral', 'Clasificacion' => 'Rural']]);
        $cubierto = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('0 entidades', $cubierto);
        $this->assertStringContainsString('Restricción de suelo: Sin cruce', $cubierto);
        $this->assertStringNotContainsString('La capa no tiene cobertura para este municipio', $cubierto);
    }

    public function test_the_report_stores_the_basemap_and_reuses_it_without_downloading_again(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $this->fingirTeselas();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);
        $this->capaConCruce($analisis, 'RUNAP_AREAS_PROTEGIDAS', 'RUNAP', [
            'ap_nombre' => 'Sierra de la Macarena',
            'ap_categoria' => 'Parque Nacional',
        ]);
        $captura = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $html = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('© OpenStreetMap contributors', $html);
        $this->assertStringContainsString('https://www.openstreetmap.org/copyright', $html);
        $this->assertStringContainsString('Captura: '.$captura, $html);
        $this->assertStringContainsString('Imagen satelital (Esri World Imagery) no incluida: los términos de Esri no permiten descargar ni almacenar las teselas.', $html);
        $this->assertStringNotContainsString('fondo gris', $html);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'arcgisonline.com') || str_contains($request->url(), 'esri.com'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'tile.openstreetmap.org'));
        $imagen = $this->imagenGuardada($analisis);
        $this->assertStringContainsString($imagen, $html);
        $this->assertElMapaCubreElPoligono($imagen);
        $this->assertStringContainsString('width: 320px', $html);
        $this->assertStringContainsString('Parque Nacional', $html);
        $this->assertSame(2, substr_count($html, 'Imagen satelital (Esri World Imagery) no incluida'));
        $mapaCapa = DB::selectOne(
            "select regexp_replace(encode(imagen, 'base64'), '\\s', '', 'g') as imagen, leyenda::text as leyenda
             from inteligencia.analisis_mapa_capa where analisis_area_id = ?",
            [$analisis->id],
        );
        $this->assertStringContainsString('Parque Nacional', (string) $mapaCapa?->leyenda);
        $this->assertStringContainsString((string) $mapaCapa?->imagen, $html);

        Http::fake();
        $repetido = app(InformePdf::class)->html($analisis->fresh());

        $this->assertStringContainsString($imagen, $repetido);
        $this->assertStringContainsString((string) $mapaCapa?->imagen, $repetido);
        $this->assertStringContainsString('Captura: '.$captura, $repetido);
        Http::assertNothingSent();
    }

    public function test_a_failed_basemap_tile_is_noted_and_the_pdf_still_renders(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'tile.openstreetmap.org/*' => Http::response('no', 500),
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = $this->analisis($admin);

        $html = app(InformePdf::class)->html($analisis->fresh());
        $pdf = app(InformePdf::class)->pdf($analisis->fresh());

        $this->assertMatchesRegularExpression('/No se pudieron descargar \d+ teselas de OpenStreetMap\. El polígono se dibujó sobre el fondo disponible\./', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertNotSame('', $this->imagenGuardada($analisis));
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'arcgisonline.com') || str_contains($request->url(), 'esri.com'));
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
        $this->geometria($cacheId, self::CUADRADO);
    }

    private function geometria(int $cacheId, string $wkt): void
    {
        DB::update(
            'update inteligencia.capa_objeto_cache set geom = ST_SetSRID(ST_GeomFromText(?), 9377) where id = ?',
            [$wkt, $cacheId],
        );
    }

    private function fingirTeselas(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'tile.openstreetmap.org/*' => Http::response($this->pngRojo(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function pngRojo(): string
    {
        $imagen = imagecreatetruecolor(256, 256);
        $rojo = imagecolorallocate($imagen, 255, 0, 0);
        imagefilledrectangle($imagen, 0, 0, 255, 255, $rojo);
        ob_start();
        imagepng($imagen);
        $png = ob_get_clean();
        imagedestroy($imagen);

        return is_string($png) ? $png : '';
    }

    private function imagenGuardada(AnalisisArea $analisis): string
    {
        $fila = DB::selectOne(
            "select encode(mapa_imagen, 'base64') as imagen from inteligencia.analisis_area where id = ?",
            [$analisis->id],
        );

        return preg_replace('/\s+/', '', is_string($fila?->imagen) ? $fila->imagen : '') ?? '';
    }

    private function assertElMapaCubreElPoligono(string $base64): void
    {
        $png = base64_decode($base64, true);
        $this->assertIsString($png);
        $imagen = imagecreatefromstring($png);
        $this->assertInstanceOf(\GdImage::class, $imagen);
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $esquina = imagecolorsforindex($imagen, imagecolorat($imagen, 0, 0));
        $centro = imagecolorsforindex($imagen, imagecolorat($imagen, (int) ($ancho / 2), (int) ($alto / 2)));
        imagedestroy($imagen);

        $this->assertLessThanOrEqual(640, $ancho);
        $this->assertLessThanOrEqual(420, $alto);
        $this->assertSame(255, $esquina['red']);
        $this->assertSame(0, $esquina['green']);
        $this->assertGreaterThan(0, $centro['green']);
    }
}
