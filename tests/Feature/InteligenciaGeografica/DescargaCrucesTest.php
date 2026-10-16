<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\UserRole;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaObjetoCache;
use App\Models\User;
use App\Services\InteligenciaGeografica\InformeAnalisis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;
use ZipArchive;

#[Group('postgis')]
class DescargaCrucesTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    private const CUADRADO = 'MULTIPOLYGON(((4910000 1990000, 4920000 1990000, 4920000 2000000, 4910000 2000000, 4910000 1990000)))';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->withoutVite();
    }

    public function test_a_guest_is_redirected_and_a_member_cannot_download_the_intersections(): void
    {
        $analisis = AnalisisArea::factory()->create();

        $this->get(route('analisis.cruces', ['analisis' => $analisis, 'formato' => 'geojson']))
            ->assertRedirect(route('login'));

        $miembro = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($miembro)
            ->get(route('analisis.cruces', ['analisis' => $analisis, 'formato' => 'kml']))
            ->assertForbidden();
    }

    public function test_an_admin_downloads_the_clipped_intersections_and_the_pages_list_them(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = AnalisisArea::factory()->create(['user_id' => $admin->id]);
        $poligonos = $this->capa($analisis, 'ANM_TITULOS_SOLICITUDES', 'Títulos', [
            'CODIGO_EXPEDIENTE' => 'EXP-1',
            '_capa' => 'titulo',
        ]);
        $this->geometria($poligonos['cache'], self::CUADRADO);
        $lineas = $this->capa($analisis, 'META_DRENAJE', 'Drenajes', [
            'NOMBRE_GEO' => 'Caño Grande',
            '_capa' => 'sencillo',
        ]);
        $this->geometria($lineas['cache'], 'LINESTRING(4910000 1995000, 4920000 1995000)');
        $ausente = Capa::factory()->create([
            'codigo' => 'SGC_AMENAZA_MOVIMIENTOS_MASA',
            'nombre' => 'Amenaza',
        ]);

        $geojson = $this->actingAs($admin)
            ->get(route('analisis.cruces', ['analisis' => $analisis, 'formato' => 'geojson']));
        $geojson->assertOk();
        $geojson->assertHeader('content-type', 'application/zip');
        $paquete = $this->zip($geojson->getContent());
        $datos = json_decode($paquete['cruces.geojson'], true);
        $txt = $paquete['metadatos.txt'];

        $this->assertSame('FeatureCollection', $datos['type']);
        $this->assertArrayNotHasKey('crs', $datos);
        $this->assertCount(2, $datos['features']);
        $porCapa = collect($datos['features'])->keyBy(fn (array $feature): string => $feature['properties']['capa']);
        $titulo = $porCapa['ANM_TITULOS_SOLICITUDES — Títulos'];
        $drenaje = $porCapa['META_DRENAJE — Drenajes'];
        $this->assertSame('EXP-1', $titulo['properties']['CODIGO_EXPEDIENTE']);
        $this->assertSame('Instituto de prueba. Capa de prueba. https://ejemplo.test/capa. Licencia de prueba.', $titulo['properties']['fuente']);
        $this->assertSame('2026-02-28 00:00:00', $titulo['properties']['fecha_corte']);
        $this->assertSame('2026-03-01 12:05:00', $titulo['properties']['fecha_consulta']);
        $this->assertEqualsWithDelta(10000, $titulo['properties']['ha'], 0.1);
        $this->assertArrayNotHasKey('km', $titulo['properties']);
        $this->assertSame('Caño Grande', $drenaje['properties']['NOMBRE_GEO']);
        $this->assertEqualsWithDelta(10, $drenaje['properties']['km'], 0.1);
        $this->assertArrayNotHasKey('ha', $drenaje['properties']);
        $coordenada = $titulo['geometry']['coordinates'];

        while (is_array($coordenada)) {
            $coordenada = $coordenada[0];
        }

        $this->assertLessThan(180, abs((float) $coordenada));
        $this->assertStringContainsString('EPSG:4326', $txt);
        $this->assertStringContainsString('Licencia de prueba', $txt);
        $this->assertStringContainsString('Fecha de corte: 2026-02-28 00:00:00', $txt);
        $this->assertStringContainsString($ausente->etiqueta().': fuente no disponible', $txt);
        $this->assertStringNotContainsString($ausente->etiqueta(), $paquete['cruces.geojson']);

        $this->actingAs($admin)
            ->get(route('analisis.capa.cruces', ['analisis' => $analisis, 'capa' => $ausente, 'formato' => 'geojson']))
            ->assertNotFound();

        $kml = $this->actingAs($admin)
            ->get(route('analisis.capa.cruces', ['analisis' => $analisis, 'capa' => $lineas['capa'], 'formato' => 'kml']));
        $kmlZip = $this->zip($kml->getContent());
        $this->assertStringContainsString('<Placemark>', $kmlZip['cruces.kml']);
        $this->assertStringContainsString('<Data name="km">', $kmlZip['cruces.kml']);
        $this->assertStringContainsString('Caño Grande', $kmlZip['cruces.kml']);
        $this->assertStringNotContainsString('EXP-1', $kmlZip['cruces.kml']);
        $this->assertStringContainsString('Licencia de prueba', $kmlZip['metadatos.txt']);

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('index', ['analisis' => $analisis->id]))
            ->assertOk()
            ->assertSee('Todos los cruces')
            ->assertSee('GeoJSON')
            ->assertSee('Pendiente de conector')
            ->assertSee('fuente no disponible');

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('historial'))
            ->assertOk()
            ->assertSee('Descargar cruces');

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('view', ['record' => $analisis]))
            ->assertRedirect(AnalisisAreaResource::getUrl('index', ['analisis' => $analisis->id]));

        $informe = view('filament.inteligencia.analisis', [
            'analisis' => $analisis,
            'geojson' => $analisis->geoJson4326(),
            'geojsonFuera' => null,
            'notaFuera' => null,
            'filas' => app(InformeAnalisis::class)->filas($analisis),
        ])->render();
        $this->assertStringContainsString('GeoJSON', $informe);
        $this->assertStringContainsString('fuente no disponible', $informe);
        $this->assertStringContainsString('Pendiente de conector', $informe);
    }

    public function test_the_shapefile_zip_includes_the_prj_and_the_metadata(): void
    {
        if (! is_executable('/usr/bin/ogr2ogr') && ! is_string(shell_exec('command -v ogr2ogr'))) {
            $this->markTestSkipped('Hace falta ogr2ogr para escribir el shapefile.');
        }

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $analisis = AnalisisArea::factory()->create(['user_id' => $admin->id]);
        $capa = $this->capa($analisis, 'RUNAP_AREAS_PROTEGIDAS', 'RUNAP', ['ap_nombre' => 'Sumapaz']);
        $this->geometria($capa['cache'], self::CUADRADO);
        Capa::factory()->create(['codigo' => 'IDEAM_COBERTURA_TIERRA_2024', 'nombre' => 'Cobertura']);

        $respuesta = $this->actingAs($admin)
            ->get(route('analisis.cruces', ['analisis' => $analisis, 'formato' => 'shp']));
        $respuesta->assertOk();
        $paquete = $this->zip($respuesta->getContent());
        $nombres = array_keys($paquete);

        $this->assertNotEmpty(array_filter($nombres, fn (string $nombre): bool => str_ends_with($nombre, '.shp')));
        $this->assertNotEmpty(array_filter($nombres, fn (string $nombre): bool => str_ends_with($nombre, '.shx')));
        $this->assertNotEmpty(array_filter($nombres, fn (string $nombre): bool => str_ends_with($nombre, '.dbf')));
        $this->assertNotEmpty(array_filter($nombres, fn (string $nombre): bool => str_ends_with($nombre, '.prj')));
        $prj = $paquete[array_values(array_filter($nombres, fn (string $nombre): bool => str_ends_with($nombre, '.prj')))[0]];
        $this->assertStringContainsString('WGS_1984', $prj);
        $this->assertStringContainsString('fuente no disponible', $paquete['metadatos.txt']);
        $this->assertStringContainsString('fecha_cort', $paquete['metadatos.txt']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array{capa: Capa, cache: int}
     */
    private function capa(AnalisisArea $analisis, string $codigo, string $nombre, array $atributos): array
    {
        $capa = Capa::factory()->create(['codigo' => $codigo, 'nombre' => $nombre]);
        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $capa->id,
            'conteo' => 1,
            'area_m2' => 10000,
            'cita_fuente' => 'Instituto de prueba. Capa de prueba. https://ejemplo.test/capa. Licencia de prueba.',
            'licencia' => 'Licencia de prueba',
        ]);
        $cache = CapaObjetoCache::factory()->create([
            'capa_id' => $capa->id,
            'atributos' => $atributos,
            'identificador_origen' => $codigo.'-1',
            'valid_from' => $resultado->version->numero,
        ]);

        return ['capa' => $capa, 'cache' => $cache->id];
    }

    private function geometria(int $cacheId, string $wkt): void
    {
        DB::update(
            'update inteligencia.capa_objeto_cache set geom = ST_SetSRID(ST_GeomFromText(?), 9377) where id = ?',
            [$wkt, $cacheId],
        );
    }

    /**
     * @return array<string, string>
     */
    private function zip(string $bytes): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents((string) $ruta, $bytes);
        $zip = new ZipArchive;
        $zip->open((string) $ruta);
        $archivos = [];

        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $nombre = $zip->getNameIndex($indice);
            $archivos[(string) $nombre] = (string) $zip->getFromIndex($indice);
        }

        $zip->close();
        unlink((string) $ruta);

        return $archivos;
    }
}
