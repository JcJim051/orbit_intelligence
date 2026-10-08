<?php

namespace Tests\Feature\Intelligence;

use App\Enums\TipoReglaPasiva;
use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Models\FuenteFinanciacion;
use App\Models\PasivaLinea;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportacionPasivaAcumuladoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_carga_por_periodo_agrega_el_acumulado_y_el_neto_de_modificaciones(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependencia = Dependencia::factory()->create();
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $dependencia->id,
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => '0301',
            'prioridad' => null,
        ]);
        $fuente = FuenteFinanciacion::factory()->create(['codigo' => '20', 'nombre' => 'Ingresos corrientes de libre destinación']);
        $seguimiento = Seguimiento::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->libro()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $linea = PasivaLinea::query()->vigentes()->sole();
        $this->assertEqualsWithDelta(970, (float) $linea->apropiacion_definitiva, 0.001);
        $this->assertEqualsWithDelta(-30, (float) $linea->modificaciones, 0.001);
        $this->assertEqualsWithDelta(400, (float) $linea->compromisos, 0.001);
        $this->assertEqualsWithDelta(300, (float) $linea->obligaciones, 0.001);
        $this->assertEqualsWithDelta(100, (float) $linea->pagos, 0.001);

        $techo = Techo::query()->sole();
        $this->assertSame($fuente->id, $techo->fuente_financiacion_id);
        $this->assertSame($dependencia->id, $techo->dependencia_id);
        $this->assertEqualsWithDelta(970, (float) $techo->valor, 0.001);
        $this->assertEqualsWithDelta(400, (float) $techo->comprometido, 0.001);
        $this->assertEqualsWithDelta(300, (float) $techo->obligado, 0.001);
        $this->assertEqualsWithDelta(100, (float) $techo->pagado, 0.001);
        $this->assertNull($techo->comprometido_ajuste);
    }

    public function test_volver_a_cargar_la_pasiva_restaura_los_valores_y_retira_el_ajuste(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependencia = Dependencia::factory()->create();
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $dependencia->id,
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => '0301',
            'prioridad' => null,
        ]);
        $fuente = FuenteFinanciacion::factory()->create(['codigo' => '20', 'nombre' => 'Ingresos corrientes de libre destinación']);
        $seguimiento = Seguimiento::factory()->create(['created_by' => $admin->id]);
        $this->actingAs($admin)
            ->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->libro()])
            ->assertSessionHasNoErrors();

        $techo = Techo::query()->sole();
        $proyecto = $techo->proyecto;

        $this->patch(route('intelligence.reporte-mensual.techos.update', $techo), [
            'fuente_financiacion_id' => $fuente->id,
            'asignado' => 970,
            'comprometido' => 999,
            'obligado' => 300,
            'pagado' => 100,
            'motivo' => 'El acumulado de compromisos quedó mal leído.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('999.00', $techo->fresh()->comprometido);

        $this->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->libro()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $techo->refresh();
        $this->assertNull($techo->comprometido_ajuste);
        $this->assertEqualsWithDelta(400, (float) $techo->comprometido_pasiva, 0.001);
        $this->assertEqualsWithDelta(400, (float) $techo->comprometido, 0.001);
        $this->assertSame($proyecto->id, $techo->proyecto_id);
        $this->assertStringContainsString('se retiró el ajuste manual anterior', (string) $techo->historial()->first()->motivo);
    }

    private function libro(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pasiva').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['GOBERNACION DEL META']));
        $writer->addRow(Row::fromValues($this->celdas([
            2 => 'APROPIACION',
            12 => 'CERTIFICADOS',
            14 => 'COMPROMISOS',
            16 => 'OBLIGACIONES',
            18 => 'PAGOS',
        ])));
        $writer->addRow(Row::fromValues($this->celdas([
            0 => 'IDENTIFICACIÓN PRESUPUESTAL',
            1 => 'CONCEPTO',
            2 => 'INICIAL',
            3 => 'CONTRACREDITOS',
            5 => 'CREDITOS',
            7 => 'REDUCCIONES',
            9 => 'ADICIONES',
            11 => 'DEFINITIVA',
        ])));
        $writer->addRow(Row::fromValues($this->celdas([
            3 => 'Acumulado',
            4 => 'Periodo',
            5 => 'Acumulado',
            6 => 'Periodo',
            7 => 'Acumulado',
            8 => 'Periodo',
            9 => 'Acumulado',
            10 => 'Periodo',
            12 => 'Acumulado',
            13 => 'Periodo',
            14 => 'Acumulado',
            15 => 'Periodo',
            16 => 'Acumulado',
            17 => 'Periodo',
            18 => 'Acumulado',
            19 => 'Periodo',
        ])));
        $writer->addRow(Row::fromValues($this->celdas([
            0 => '0301 - 2.3.45.4503.1000.001',
            1 => 'BPIN 2024005500085. PROYECTO DE PRUEBA',
            2 => 1000,
            11 => 970,
        ])));
        $writer->addRow(Row::fromValues($this->celdas([
            0 => '0301 - 2.3.45.4503.1000.001.2.3.2.02.02.008 - 20',
            1 => 'Servicios de prueba',
            2 => 1000,
            3 => 100,
            4 => 10,
            5 => 50,
            6 => 5,
            9 => 20,
            10 => 1,
            11 => 970,
            12 => 10,
            13 => 1,
            14 => 400,
            15 => 50,
            16 => 300,
            17 => 40,
            18 => 100,
            19 => 20,
        ])));
        $writer->close();

        return new UploadedFile($path, 'pasiva_periodo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @param  array<int, mixed>  $valores
     * @return list<mixed>
     */
    private function celdas(array $valores): array
    {
        $fila = array_fill(0, 20, '');

        foreach ($valores as $indice => $valor) {
            $fila[$indice] = $valor;
        }

        return $fila;
    }
}
