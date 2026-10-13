<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\EstadoSincronizacionCapa;
use App\Enums\FrecuenciaActualizacionCapa;
use App\Enums\ModoAnalisis;
use App\Enums\ServidoDesde;
use App\Enums\UserRole;
use App\Filament\Resources\CapaResource;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaVersion;
use App\Models\User;
use App\Services\InteligenciaGeografica\CargarCapaManual;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use App\Services\InteligenciaGeografica\Conectores\DefinicionCapaAbierta;
use App\Services\InteligenciaGeografica\CrearAnalisisArea;
use App\Services\InteligenciaGeografica\InformeAnalisis;
use App\Services\InteligenciaGeografica\LectorArchivoCapa;
use App\Services\InteligenciaGeografica\SincronizarCapa;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;
use ZipArchive;

#[Group('postgis')]
class SincronizacionCapasAbiertasTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    private const CUADRADO = '{"type":"Polygon","coordinates":[[[-73.78,3.97],[-73.75,3.97],[-73.75,4.00],[-73.78,4.00],[-73.78,3.97]]]}';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
    }

    public function test_sync_downloads_reprojects_and_skips_the_next_run_when_the_cutoff_is_unchanged(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respuestaRunap($request, false));
        $capa = $this->capa('RUNAP_AREAS_PROTEGIDAS', 'RUNAP');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);
        $capa->refresh();

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $corrida->estado, (string) $corrida->mensaje_error);
        $this->assertTrue($corrida->cambio);
        $this->assertSame(2, $corrida->entidades_agregadas);
        $this->assertSame('2026-10-09 21:08:31', $corrida->fecha_corte_detectada?->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('No declarada en el servicio.', $corrida->licencia_detectada);
        $this->assertStringContainsString('runap/FeatureServer/0', (string) $corrida->url_consultada);
        $this->assertSame(64, strlen((string) $corrida->checksum));
        $this->assertNotNull($corrida->finalizado_at);
        $this->assertTrue($corrida->finalizado_at->greaterThanOrEqualTo($corrida->iniciado_at));
        $this->assertSame(EstadoFrescuraCapa::Vigente, $capa->estado_frescura);
        $this->assertSame(2, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());
        $this->assertSame(9377, (int) DB::selectOne(
            'select ST_SRID(geom) as srid from inteligencia.capa_objeto_cache where capa_id = ? limit 1',
            [$capa->id],
        )->srid);

        $consultas = collect(Http::recorded())->filter(
            fn (array $par): bool => $par[0]->method() === 'POST' && str_contains($par[0]->url(), 'runap/FeatureServer/0/query'),
        )->count();

        $siguiente = app(SincronizarCapa::class)->ejecutar($capa->refresh());

        $this->assertSame(EstadoSincronizacionCapa::SinCambios, $siguiente->estado);
        $this->assertFalse($siguiente->cambio);
        $this->assertSame(0, $siguiente->entidades_agregadas);
        $this->assertSame($corrida->checksum, $siguiente->checksum);
        $this->assertSame(1, $capa->versiones()->count());
        $this->assertSame($consultas, collect(Http::recorded())->filter(
            fn (array $par): bool => $par[0]->method() === 'POST' && str_contains($par[0]->url(), 'runap/FeatureServer/0/query'),
        )->count());
    }

    public function test_pagination_keeps_every_page_and_a_repeated_checksum_does_not_rewrite_counts(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respuestaDane($request));
        $capa = $this->capa('DANE_GRILLA_POBLACION_1KM', 'DANE');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $corrida->estado);
        $this->assertSame(2, $corrida->entidades_agregadas);
        $this->assertNull($corrida->fecha_corte_detectada);
        $this->assertStringContainsString('no publica fecha de corte', (string) $corrida->nota);
        $this->assertSame(2, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());

        $offsets = collect(Http::recorded())
            ->filter(fn (array $par): bool => $par[0]->method() === 'POST')
            ->map(fn (array $par): int => (int) ($par[0]->data()['resultOffset'] ?? -1))
            ->values()
            ->all();
        $this->assertSame([0, 1000], $offsets);

        $siguiente = app(SincronizarCapa::class)->ejecutar($capa->refresh());

        $this->assertSame(EstadoSincronizacionCapa::RepublicadoSinCambios, $siguiente->estado);
        $this->assertSame(0, $siguiente->entidades_agregadas + $siguiente->entidades_actualizadas + $siguiente->entidades_eliminadas);
        $this->assertSame(1, $capa->versiones()->count());
        $this->assertSame(2, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->whereNull('valid_to')->count());
    }

    public function test_a_layer_without_pagination_is_downloaded_by_object_ids(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respuestaSgc($request));
        $capa = $this->capa('SGC_AMENAZA_MOVIMIENTOS_MASA', 'SGC');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $corrida->estado, (string) $corrida->mensaje_error);
        $this->assertSame(2, $corrida->entidades_agregadas);
        $this->assertNull($corrida->fecha_corte_detectada);
        $this->assertStringContainsString('no publica fecha de corte', (string) $corrida->nota);
        $this->assertSame(2, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());

        $consultas = collect(Http::recorded())
            ->filter(fn (array $par): bool => $par[0]->method() === 'POST' && str_contains($par[0]->url(), 'CapasTematicasXEscalas'))
            ->map(fn (array $par): array => $par[0]->data())
            ->values();

        $this->assertTrue($consultas->contains(fn (array $datos): bool => ($datos['returnIdsOnly'] ?? null) === 'true'));
        $this->assertTrue($consultas->contains(fn (array $datos): bool => isset($datos['objectIds'])));
        $this->assertFalse($consultas->contains(fn (array $datos): bool => array_key_exists('resultOffset', $datos)));
    }

    public function test_a_missing_geometry_does_not_store_a_partial_copy(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->respuestaSgcIncompleta($request));
        $capa = $this->capa('SGC_AMENAZA_MOVIMIENTOS_MASA', 'SGC');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::FuenteNoDisponible, $corrida->estado);
        $this->assertStringContainsString('11', (string) $corrida->mensaje_error);
        $this->assertStringContainsString('12', (string) $corrida->mensaje_error);
        $this->assertStringContainsString('incompleta', (string) $corrida->mensaje_error);
        $this->assertSame(0, $corrida->entidades_agregadas);
        $this->assertSame(0, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());
    }

    public function test_an_html_error_page_is_stored_as_the_sync_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request): mixed {
            if (! str_contains($request->url(), '/query') && $request->method() !== 'POST') {
                return Http::response([
                    'maxRecordCount' => 1000,
                    'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
                ]);
            }

            return Http::response(
                '<html><p class="errorLabel">Could not access any server machines.</p></html>',
                200,
                ['Content-Type' => 'text/html'],
            );
        });
        $capa = $this->capa('DANE_GRILLA_POBLACION_1KM', 'DANE');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::FuenteNoDisponible, $corrida->estado);
        $this->assertStringContainsString('Could not access any server machines.', (string) $corrida->mensaje_error);
        $this->assertStringContainsString('2 intentos', (string) $corrida->nota);
        $this->assertSame(0, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());
    }

    public function test_an_arcgis_error_payload_stays_a_failed_sync(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request): mixed {
            if (! str_contains($request->url(), '/query') && $request->method() !== 'POST') {
                return Http::response([
                    'maxRecordCount' => 1000,
                    'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
                ]);
            }

            return Http::response(['error' => ['message' => 'Pagination is not supported']]);
        });
        $capa = $this->capa('DANE_GRILLA_POBLACION_1KM', 'DANE');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::Fallido, $corrida->estado);
        $this->assertStringContainsString('Pagination is not supported', (string) $corrida->mensaje_error);
    }

    public function test_the_igac_package_skips_villavicencio_and_keeps_the_publication_date(): void
    {
        if (! is_executable('/usr/bin/ogr2ogr') && ! is_string(shell_exec('command -v ogr2ogr'))) {
            $this->markTestSkipped('Hace falta ogr2ogr para leer la geodatabase.');
        }

        Http::preventStrayRequests();
        $zip = $this->zipGeodatabase();
        Http::fake(function (Request $request) use ($zip): mixed {
            if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/data')) {
                return Http::response($zip, 200, ['Content-Type' => 'application/zip']);
            }

            return Http::response([
                'modified' => 1790020676000,
                'licenseInfo' => 'CC BY-SA 4.0',
            ]);
        });
        $capa = $this->capa('IGAC_CATASTRO_R1', 'IGAC');

        $corrida = app(SincronizarCapa::class)->ejecutar($capa);

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $corrida->estado, (string) $corrida->mensaje_error);
        $this->assertSame(2, $corrida->entidades_agregadas);
        $this->assertSame('2026-09-21 19:57:56', $corrida->fecha_corte_detectada?->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('CC BY-SA 4.0', $corrida->licencia_detectada);
        $this->assertStringContainsString('50001', (string) $corrida->nota);
        $this->assertStringContainsString('no cubierto: gestor propio', (string) $corrida->nota);
        $this->assertStringContainsString('Municipios con terrenos de IGAC: 2', (string) $corrida->nota);
        $this->assertSame(0, DB::table('inteligencia.capa_objeto_cache')->where('atributos->codigo_municipio', '50001')->count());
        $this->assertSame(9377, (int) DB::selectOne(
            'select ST_SRID(geom) as srid from inteligencia.capa_objeto_cache where capa_id = ? limit 1',
            [$capa->id],
        )->srid);
        $this->assertFalse(collect(Http::recorded())->contains(
            fn (array $par): bool => str_contains($par[0]->url(), 'Dato_Fundamental_Catastro'),
        ));

        $descargas = collect(Http::recorded())->filter(
            fn (array $par): bool => $par[0]->method() === 'GET' && str_ends_with((string) parse_url($par[0]->url(), PHP_URL_PATH), '/data'),
        )->count();

        $siguiente = app(SincronizarCapa::class)->ejecutar($capa->refresh());

        $this->assertSame(EstadoSincronizacionCapa::SinCambios, $siguiente->estado);
        $this->assertSame(1, $capa->versiones()->count());
        $this->assertSame($descargas, collect(Http::recorded())->filter(
            fn (array $par): bool => $par[0]->method() === 'GET' && str_ends_with((string) parse_url($par[0]->url(), PHP_URL_PATH), '/data'),
        )->count());
    }

    public function test_manual_upload_requires_source_and_cutoff_and_then_feeds_the_analysis(): void
    {
        $admin = User::factory()->create();
        $this->app->instance(CatalogoCapasAbiertas::class, new class extends CatalogoCapasAbiertas
        {
            public function definicion(string $codigo): ?DefinicionCapaAbierta
            {
                $definicion = parent::definicion($codigo);

                if ($definicion === null || $codigo !== 'IGAC_CATASTRO_R1') {
                    return $definicion;
                }

                return new DefinicionCapaAbierta(
                    codigo: $definicion->codigo,
                    disponible: false,
                    licencia: $definicion->licencia,
                    cita: $definicion->cita,
                    observacion: $definicion->observacion,
                    urlServicio: $definicion->urlServicio,
                    layerId: $definicion->layerId,
                    endpoints: $definicion->endpoints,
                    motivoNoDisponible: 'Fuente de prueba no disponible.',
                    itemUrl: $definicion->itemUrl,
                    capasPaquete: $definicion->capasPaquete,
                );
            }
        });
        $capa = $this->capa('IGAC_CATASTRO_R1', 'IGAC');
        $cargar = app(CargarCapaManual::class);

        try {
            $cargar->cargar($capa, 'predios.geojson', self::CUADRADO, '', '2024-05-01', null);
            $this->fail('El cargue sin fuente debió rechazarse.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('cita_fuente', $excepcion->errors());
        }

        try {
            $cargar->cargar($capa, 'predios.geojson', self::CUADRADO, 'IGAC. Extracto departamental entregado por convenio.', '', null);
            $this->fail('El cargue sin fecha de corte debió rechazarse.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('fecha_corte', $excepcion->errors());
        }

        $corrida = $cargar->cargar(
            $capa,
            'predios.geojson',
            self::CUADRADO,
            'IGAC. Extracto departamental entregado por convenio.',
            '2024-05-01',
            null,
        );
        $capa->refresh();

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $corrida->estado);
        $this->assertSame('cargue manual', $corrida->url_consultada);
        $this->assertSame('No declarada en el cargue.', $corrida->licencia_detectada);
        $this->assertSame('2024-05-01 05:00:00', $capa->fecha_corte_fuente?->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame(1, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());

        $zip = $cargar->cargar(
            $capa,
            'predios.zip',
            $this->zipShapefile(),
            'IGAC. Shapefile departamental.',
            '2024-06-01',
            'Uso interno del convenio',
        );
        $this->assertSame(1, $zip->entidades_actualizadas + $zip->entidades_agregadas);
        $this->assertSame('Uso interno del convenio', $zip->licencia_detectada);

        $analisis = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Normal, null, self::CUADRADO, null, null);
        $resultado = $analisis->resultados()->where('capa_id', $capa->id)->first();

        $this->assertNotNull($resultado);
        $this->assertNotNull($resultado->capa_version_id);
        $this->assertSame(1, $resultado->conteo);
        $this->assertSame(ServidoDesde::Copia, $resultado->servido_desde);
        $this->assertSame('IGAC. Shapefile departamental.', $resultado->cita_fuente);
    }

    public function test_shp_projection_must_be_one_of_the_supported_codes(): void
    {
        $lector = app(LectorArchivoCapa::class);

        $this->assertSame(4326, $lector->sridDePrj('GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984"]]'));

        $this->expectException(\InvalidArgumentException::class);
        $lector->sridDePrj('PROJCS["MAGNA_Bogota",GEOGCS["GCS_MAGNA"]]');
    }

    public function test_official_mode_queries_the_live_source_and_falls_back_to_the_copy(): void
    {
        Http::preventStrayRequests();
        $caer = false;
        Http::fake(function (Request $request) use (&$caer) {
            if ($caer) {
                return Http::response(['error' => ['message' => 'caida']], 200);
            }

            return $this->respuestaRunap($request, false);
        });
        $admin = User::factory()->create();
        $capa = $this->capa('RUNAP_AREAS_PROTEGIDAS', 'RUNAP');
        app(SincronizarCapa::class)->ejecutar($capa);

        $oficial = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Oficial, null, self::CUADRADO, null, null);
        $directa = $oficial->resultados()->where('capa_id', $capa->id)->first();

        $this->assertNotNull($directa);
        $this->assertSame(ServidoDesde::FuenteDirecta, $directa->servido_desde);
        $this->assertFalse($directa->obsoleto);
        $this->assertGreaterThan(0, $directa->conteo);

        $caer = true;
        $respaldo = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Oficial, null, self::CUADRADO, null, null);
        $copia = $respaldo->resultados()->where('capa_id', $capa->id)->first();

        $this->assertNotNull($copia);
        $this->assertSame(ServidoDesde::CopiaPorFallaFuente, $copia->servido_desde);
        $this->assertTrue($copia->obsoleto);
        $this->assertSame('No se consultó la fuente. Se usó la copia local.', $copia->resumen['advertencia'] ?? null);
    }

    public function test_the_scheduler_respects_cadence_until_a_layer_or_force_flag_is_given(): void
    {
        Http::preventStrayRequests();
        $capa = $this->capa('RUNAP_AREAS_PROTEGIDAS', 'RUNAP');
        $capa->forceFill([
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::Semanal,
            'fecha_ultima_sincronizacion' => now(),
            'fecha_corte_fuente' => now()->subDay(),
        ])->save();

        Artisan::call('inteligencia:sincronizar');
        $this->assertStringContainsString('dentro de la cadencia', Artisan::output());
        Http::assertNothingSent();

        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn (object $evento): bool => str_contains((string) $evento->command, 'inteligencia:sincronizar'));

        $this->assertCount(1, $eventos);
        $this->assertTrue($eventos->first()->withoutOverlapping);

        Http::fake(fn (Request $request) => $this->respuestaRunap($request, false));
        Artisan::call('inteligencia:sincronizar', ['capa' => 'RUNAP_AREAS_PROTEGIDAS']);
        $this->assertSame(1, $capa->sincronizaciones()->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'runap/FeatureServer/0'));
    }

    public function test_a_republished_layer_with_the_same_content_does_not_open_a_version(): void
    {
        Http::preventStrayRequests();
        $edicion = 1700000000000;
        Http::fake(function (Request $request) use (&$edicion): mixed {
            return $this->respuestaPot($request, $edicion, [
                $this->feature('1', json_decode(self::CUADRADO, true), 'OBJECTID', ['marca' => 'igual']),
            ]);
        });
        $capa = $this->capa('CORMACARENA_POT_CLASIFICACION', 'CORMACARENA');

        $primera = app(SincronizarCapa::class)->ejecutar($capa);
        $edicion += 86_400_000;
        $segunda = app(SincronizarCapa::class)->ejecutar($capa->refresh());

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $primera->estado, (string) $primera->mensaje_error);
        $this->assertSame(EstadoSincronizacionCapa::RepublicadoSinCambios, $segunda->estado);
        $this->assertSame(1, CapaVersion::query()->where('capa_id', $capa->id)->count());
        $this->assertTrue(CapaVersion::query()->where('capa_id', $capa->id)->where('numero', 1)->value('vigente'));
        $this->assertSame(1, DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->count());
    }

    public function test_a_content_change_archives_the_previous_version_and_alerts_over_twenty_percent(): void
    {
        Http::preventStrayRequests();
        $edicion = 1700000000000;
        $features = [
            $this->feature('1', json_decode(self::CUADRADO, true), 'OBJECTID', ['marca' => 'v1']),
        ];
        Http::fake(function (Request $request) use (&$edicion, &$features): mixed {
            return $this->respuestaPot($request, $edicion, $features);
        });
        $capa = $this->capa('CORMACARENA_POT_CLASIFICACION', 'CORMACARENA');
        app(SincronizarCapa::class)->ejecutar($capa);

        $edicion += 86_400_000;
        $features = [
            $this->feature('1', json_decode(self::CUADRADO, true), 'OBJECTID', ['marca' => 'v2']),
            $this->feature('2', json_decode(self::CUADRADO, true), 'OBJECTID', ['marca' => 'nueva']),
        ];
        $segunda = app(SincronizarCapa::class)->ejecutar($capa->refresh());
        $capa->refresh();

        $this->assertSame(EstadoSincronizacionCapa::Exitoso, $segunda->estado, (string) $segunda->mensaje_error);
        $this->assertSame(1, $segunda->entidades_agregadas);
        $this->assertSame(1, $segunda->entidades_actualizadas);
        $this->assertSame(0, $segunda->entidades_eliminadas);
        $this->assertSame(2, $capa->versionVigente?->numero);
        $this->assertFalse((bool) CapaVersion::query()->where('capa_id', $capa->id)->where('numero', 1)->value('vigente'));
        $this->assertSame(2, (int) DB::table('inteligencia.capa_objeto_cache')->where('identificador_origen', 'pot:1')->where('atributos->marca', 'v1')->value('valid_to'));
        $this->assertNull(DB::table('inteligencia.capa_objeto_cache')->where('identificador_origen', 'pot:1')->where('atributos->marca', 'v2')->value('valid_to'));
        $this->assertStringContainsString('20%', (string) $capa->alerta);

        $capa->forceFill([
            'ttl_horas' => 24,
            'fecha_ultima_sincronizacion' => now()->subDays(3),
        ])->save();

        $this->withoutVite();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(CapaResource::getUrl('index'))
            ->assertOk()
            ->assertSee('más del 20%')
            ->assertSee('h de vigencia')
            ->assertSee('Versión vigente');
    }

    public function test_an_analysis_keeps_the_version_used_after_a_newer_copy_arrives(): void
    {
        Http::preventStrayRequests();
        $edicion = 1700000000000;
        $marca = 'v1';
        Http::fake(function (Request $request) use (&$edicion, &$marca): mixed {
            return $this->respuestaPot($request, $edicion, [
                $this->feature('1', json_decode(self::CUADRADO, true), 'OBJECTID', ['marca' => $marca]),
            ]);
        });
        $admin = User::factory()->create();
        $capa = $this->capa('CORMACARENA_POT_CLASIFICACION', 'CORMACARENA');
        app(SincronizarCapa::class)->ejecutar($capa);
        $versionInicial = $capa->refresh()->versionVigente;

        $analisis = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Normal, null, self::CUADRADO, null, null);
        $resultado = $analisis->resultados()->where('capa_id', $capa->id)->first();

        $edicion += 86_400_000;
        $marca = 'v2';
        app(SincronizarCapa::class)->ejecutar($capa->refresh());

        $detalles = app(InformeAnalisis::class)->filas($analisis->refresh())->firstWhere('id', $capa->id)['detalles'];
        $nuevo = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Normal, null, self::CUADRADO, null, null);
        $resultadoNuevo = $nuevo->resultados()->where('capa_id', $capa->id)->first();

        $this->assertNotNull($resultado);
        $this->assertSame($versionInicial?->id, $resultado->capa_version_id);
        $this->assertStringContainsString('v1', $detalles[0]['atributos'] ?? '');
        $this->assertNotNull($resultadoNuevo);
        $this->assertSame($capa->refresh()->versionVigente?->id, $resultadoNuevo->capa_version_id);
        $this->assertNotSame($resultado->capa_version_id, $resultadoNuevo->capa_version_id);
    }

    public function test_manual_upload_is_rejected_when_the_public_source_exists(): void
    {
        $capa = $this->capa('RUNAP_AREAS_PROTEGIDAS', 'RUNAP');

        $this->expectException(ValidationException::class);
        app(CargarCapaManual::class)->cargar($capa, 'predios.geojson', self::CUADRADO, 'Una cita', '2024-05-01', null);
    }

    private function zipGeodatabase(): string
    {
        $directorio = sys_get_temp_dir().'/igac-fixture-'.bin2hex(random_bytes(4));
        mkdir($directorio);
        file_put_contents($directorio.'/rural.geojson', json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                $this->predio('500060001', '50006'),
                $this->predio('500010001', '50001'),
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($directorio.'/urbano.geojson', json_encode([
            'type' => 'FeatureCollection',
            'features' => [$this->predio('503130001', '50313')],
        ], JSON_THROW_ON_ERROR));

        (new Process(['ogr2ogr', '-f', 'OpenFileGDB', $directorio.'/50.gdb', $directorio.'/rural.geojson', '-nln', 'R_TERRENO']))->mustRun();
        (new Process(['ogr2ogr', '-f', 'OpenFileGDB', '-update', $directorio.'/50.gdb', $directorio.'/urbano.geojson', '-nln', 'U_TERRENO']))->mustRun();

        $ruta = $directorio.'/paquete.zip';
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::CREATE);
        $base = $directorio.'/50.gdb';
        foreach (scandir($base) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }
            $zip->addFile($base.'/'.$nombre, '50.gdb/'.$nombre);
        }
        $zip->close();
        $contenido = (string) file_get_contents($ruta);

        return $contenido;
    }

    /**
     * @return array<string, mixed>
     */
    private function predio(string $codigo, string $municipio): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                'CODIGO' => $codigo,
                'codigo_municipio' => $municipio,
                'CODIGO_DEPARTAMENTO' => '50',
            ],
            'geometry' => json_decode(self::CUADRADO, true),
        ];
    }

    private function capa(string $codigo, string $nombre): Capa
    {
        return Capa::factory()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activa' => true,
        ]);
    }

    private function respuestaRunap(Request $request, bool $segundaPagina): mixed
    {
        $url = $request->url();

        if (str_contains($url, 'latest.zip')) {
            return Http::response('', 200, ['Last-Modified' => 'Fri, 09 Oct 2026 21:08:31 GMT']);
        }

        if (str_contains($url, 'Límite_Departamental_Meta') || str_contains($url, 'Limite_Departamental_Meta')) {
            if (! str_contains($url, '/query') && $request->method() !== 'POST') {
                return Http::response(['maxRecordCount' => 10, 'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']]]);
            }

            return Http::response($this->coleccion([
                $this->feature('limite', $this->cuadrado(-75, 1, -70, 5)),
            ]));
        }

        if (! str_contains($url, '/query') && $request->method() !== 'POST') {
            return Http::response([
                'maxRecordCount' => 1000,
                'description' => 'download_at: https://storage.googleapis.com/pnn_geodatabase/runap/latest.zip',
                'fields' => [['name' => 'objectid', 'type' => 'esriFieldTypeOID']],
                'editingInfo' => null,
            ]);
        }

        return Http::response($this->coleccion([
            $this->feature('ap-1', json_decode(self::CUADRADO, true)),
            $this->feature('ap-2', json_decode(self::CUADRADO, true)),
        ], $segundaPagina));
    }

    private function respuestaSgc(Request $request): mixed
    {
        $url = $request->url();

        if (str_contains($url, 'Límite_Departamental_Meta') || str_contains($url, 'Limite_Departamental_Meta')) {
            if (! str_contains($url, '/query') && $request->method() !== 'POST') {
                return Http::response(['maxRecordCount' => 10, 'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']]]);
            }

            return Http::response($this->coleccion([
                $this->feature('limite', $this->cuadrado(-75, 1, -70, 5)),
            ]));
        }

        if (! str_contains($url, '/query') && $request->method() !== 'POST') {
            return Http::response([
                'maxRecordCount' => 1000,
                'advancedQueryCapabilities' => ['supportsPagination' => false],
                'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
            ]);
        }

        if (($request->data()['returnIdsOnly'] ?? null) === 'true') {
            return Http::response(['objectIdFieldName' => 'OBJECTID', 'objectIds' => [11, 12]]);
        }

        return Http::response($this->coleccion([
            $this->feature('11', json_decode(self::CUADRADO, true), 'OBJECTID'),
            $this->feature('12', json_decode(self::CUADRADO, true), 'OBJECTID'),
        ]));
    }

    private function respuestaSgcIncompleta(Request $request): mixed
    {
        if (($request->data()['objectIds'] ?? null) === '11') {
            throw new ConnectionException('cURL error 28: Operation timed out after 90000 milliseconds with 0 bytes received');
        }

        if (($request->data()['objectIds'] ?? null) === '12') {
            return Http::response(
                '<html><p class="errorLabel">Could not access any server machines.</p></html>',
                200,
                ['Content-Type' => 'text/html'],
            );
        }

        return $this->respuestaSgc($request);
    }

    private function respuestaDane(Request $request): mixed
    {
        if (! str_contains($request->url(), '/query') && $request->method() !== 'POST') {
            return Http::response([
                'maxRecordCount' => 1000,
                'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
            ]);
        }

        $desplazamiento = (int) ($request->data()['resultOffset'] ?? 0);
        $codigo = $desplazamiento === 0 ? 'n6-1' : 'n6-2';

        return Http::response($this->coleccion([
            $this->feature($codigo, json_decode(self::CUADRADO, true), 'n6_cod'),
        ], $desplazamiento === 0));
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function coleccion(array $features, bool $hayMas = false): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'exceededTransferLimit' => $hayMas,
        ];
    }

    /**
     * @param  array<string, mixed>  $geometria
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $geometria
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function feature(string $id, array $geometria, string $campo = 'ap_id', array $extra = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => [$campo => $id, ...$extra],
            'geometry' => $geometria,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     */
    private function respuestaPot(Request $request, int $edicion, array $features): mixed
    {
        if (str_contains($request->url(), '/query') || $request->method() === 'POST') {
            return Http::response($this->coleccion($features));
        }

        if (str_contains($request->url(), 'sharing/rest/content/items')) {
            return Http::response([
                'modified' => $edicion,
                'size' => 10,
                'licenseInfo' => '',
            ]);
        }

        return Http::response([
            'maxRecordCount' => 1000,
            'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
            'editingInfo' => ['dataLastEditDate' => $edicion],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cuadrado(float $oeste, float $sur, float $este, float $norte): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[[$oeste, $sur], [$este, $sur], [$este, $norte], [$oeste, $norte], [$oeste, $sur]]],
        ];
    }

    private function zipShapefile(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'shp');
        $zip = new ZipArchive;
        $zip->open((string) $ruta, ZipArchive::OVERWRITE);
        $zip->addFromString('capa.shp', $this->shpCuadrado());
        $zip->addFromString('capa.dbf', $this->dbfId('p1'));
        $zip->addFromString('capa.prj', 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137.0,298.257223563]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]]');
        $zip->close();
        $contenido = (string) file_get_contents((string) $ruta);
        @unlink((string) $ruta);

        return $contenido;
    }

    private function shpCuadrado(): string
    {
        $puntos = [[-73.78, 3.97], [-73.75, 3.97], [-73.75, 4.00], [-73.78, 4.00], [-73.78, 3.97]];
        $cuerpo = pack('V', 5);
        $cuerpo .= pack('dddd', -73.78, 3.97, -73.75, 4.00);
        $cuerpo .= pack('VV', 1, count($puntos));
        $cuerpo .= pack('V', 0);

        foreach ($puntos as [$x, $y]) {
            $cuerpo .= pack('dd', $x, $y);
        }

        $registro = pack('NN', 1, (int) (strlen($cuerpo) / 2)).$cuerpo;
        $cabecera = pack('NNNNNN', 9994, 0, 0, 0, 0, 0);
        $cabecera .= pack('N', (int) ((100 + strlen($registro)) / 2));
        $cabecera .= pack('VV', 1000, 5);
        $cabecera .= pack('dddddddd', -73.78, 3.97, -73.75, 4.00, 0, 0, 0, 0);

        return $cabecera.$registro;
    }

    private function dbfId(string $id): string
    {
        $campo = str_pad('id', 11, "\0").'C'.pack('V', 0).chr(10).chr(0).str_repeat("\0", 14);
        $cabecera = chr(3).chr(26).chr(1).chr(1).pack('V', 1).pack('v', 65).pack('v', 11).str_repeat("\0", 20);

        return $cabecera.$campo.chr(0x0D).' '.str_pad($id, 10, ' ');
    }
}
