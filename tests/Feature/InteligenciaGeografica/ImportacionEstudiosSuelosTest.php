<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\Municipio;
use App\Models\Suelos\EstudioSuelos;
use App\Models\User;
use App\Services\Suelos\LibroEstudiosSuelos;
use Database\Seeders\SuelosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class ImportacionEstudiosSuelosTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->seed(SuelosCatalogoSeeder::class);
    }

    public function test_a_failed_row_rolls_back_every_study_in_the_workbook(): void
    {
        $dependencia = Dependencia::factory()->create(['codigo' => 'DEP-GEO']);
        $municipio = Municipio::factory()->create(['codigo_dane' => '50001']);
        $usuario = User::factory()->create(['role' => UserRole::Admin]);
        $ruta = $this->libro([
            'Estudio' => [
                $this->estudio('EST-1', $dependencia->codigo, $municipio->codigo_dane),
                $this->estudio('EST-2', $dependencia->codigo, '99999'),
            ],
        ]);

        try {
            app(LibroEstudiosSuelos::class)->importar($ruta, $usuario);
            $this->fail('Una fila inválida debe rechazar el libro completo.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('archivo', $excepcion->errors());
        }

        $this->assertSame(0, EstudioSuelos::query()->count());
    }

    public function test_a_complete_workbook_stores_the_study_and_the_exploration_in_epsg_9377(): void
    {
        $dependencia = Dependencia::factory()->create(['codigo' => 'DEP-GEO']);
        $municipio = Municipio::factory()->create(['codigo_dane' => '50001']);
        $usuario = User::factory()->create(['role' => UserRole::Admin]);
        $ruta = $this->libro([
            'Estudio' => [
                $this->estudio('EST-1', $dependencia->codigo, $municipio->codigo_dane),
            ],
            'Exploraciones' => [[
                'estudio' => 'EST-1',
                'codigo' => 'S-1',
                'tipo_exploracion' => 'SPT',
                'x_original' => '-73.630000',
                'y_original' => '4.150000',
                'sistema_coordenadas' => 'EPSG:4326',
                'metodo_coordenadas' => 'GNSS_NAV',
                'profundidad_total_m' => '6',
                'nivel_freatico_encontrado' => '0',
            ]],
        ]);

        app(LibroEstudiosSuelos::class)->importar($ruta, $usuario);

        $estudio = EstudioSuelos::query()->where('codigo', 'EST-1')->firstOrFail();
        $this->assertSame('CARGADO', $estudio->estadoValidacion->codigo);
        $this->assertSame($usuario->id, $estudio->cargado_por);
        $this->assertSame(1, $estudio->exploraciones()->count());

        $srid = DB::selectOne('select ST_SRID(geom) as srid from suelos.exploracion where codigo = ?', ['S-1']);
        $this->assertSame(9377, (int) $srid->srid);
    }

    /**
     * @param  array<string, list<array<string, string>>>  $filasPorHoja
     */
    private function libro(array $filasPorHoja): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer;
        $writer->openToFile($ruta);
        $primera = true;

        foreach (LibroEstudiosSuelos::HOJAS as $nombre => $encabezados) {
            if ($primera) {
                $writer->getCurrentSheet()->setName($nombre);
                $primera = false;
            } else {
                $writer->addNewSheetAndMakeItCurrent()->setName($nombre);
            }

            $writer->addRow(Row::fromValues($encabezados));

            foreach ($filasPorHoja[$nombre] ?? [] as $fila) {
                $writer->addRow(Row::fromValues(array_map(
                    fn (string $columna): string => $fila[$columna] ?? '',
                    $encabezados,
                )));
            }
        }

        $writer->close();

        return $ruta;
    }

    /**
     * @return array<string, string>
     */
    private function estudio(string $codigo, string $dependencia, string $municipio): array
    {
        return [
            'codigo' => $codigo,
            'titulo' => 'Estudio de prueba',
            'tipo_estudio' => 'EDIF',
            'dependencia' => $dependencia,
            'sin_bpin_justificacion' => 'El predio todavía no tiene proyecto de inversión registrado.',
            'consultor_nombre' => 'Consultor de prueba',
            'fecha_estudio' => '2026-01-15',
            'municipio' => $municipio,
        ];
    }
}
