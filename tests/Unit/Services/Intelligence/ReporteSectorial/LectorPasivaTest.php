<?php

namespace Tests\Unit\Services\Intelligence\ReporteSectorial;

use App\Services\Intelligence\ReporteSectorial\LectorPasiva;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class LectorPasivaTest extends TestCase
{
    public function test_el_libro_por_periodo_toma_acumulado_y_no_periodo(): void
    {
        $filas = (new LectorPasiva)->leer(base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.xlsx'), 'xlsx');
        $rubro = '2.3.45.4503.1000.001';

        $veinteRb = $this->fila($filas, $rubro.'.2.3.2.02.02.008 - 20RB');
        $this->assertEqualsWithDelta(100289523.35, LectorPasiva::numero($veinteRb['valores']['apropiacion_definitiva']), 0.001);
        $this->assertEqualsWithDelta(86800000, LectorPasiva::numero($veinteRb['valores']['compromisos']), 0.001);
        $this->assertEqualsWithDelta(7000000, LectorPasiva::numero($veinteRb['valores']['obligaciones']), 0.001);
        $this->assertEqualsWithDelta(7000000, LectorPasiva::numero($veinteRb['valores']['pagos']), 0.001);
        $this->assertNotEqualsWithDelta(25200000, LectorPasiva::numero($veinteRb['valores']['compromisos']), 0.001);

        $this->assertEqualsWithDelta(9858648220, $this->sumar($filas, $rubro, '20', 'apropiacion_definitiva'), 0.001);
        $this->assertEqualsWithDelta(9671648510, $this->sumar($filas, $rubro, '20', 'compromisos'), 0.001);
        $this->assertEqualsWithDelta(4200347270, $this->sumar($filas, $rubro, '20', 'obligaciones'), 0.001);
        $this->assertEqualsWithDelta(4200347270, $this->sumar($filas, $rubro, '20', 'pagos'), 0.001);
        $this->assertEqualsWithDelta(5000000000, $this->sumar($filas, $rubro, '100I', 'apropiacion_definitiva'), 0.001);
        $this->assertEqualsWithDelta(0, $this->sumar($filas, $rubro, '100I', 'compromisos'), 0.001);
        $this->assertEqualsWithDelta(240289523.35, $this->sumar($filas, $rubro, '20RB', 'apropiacion_definitiva'), 0.001);
        $this->assertEqualsWithDelta(226781000, $this->sumar($filas, $rubro, '20RB', 'compromisos'), 0.001);
        $this->assertEqualsWithDelta(7000000, $this->sumar($filas, $rubro, '20RB', 'obligaciones'), 0.001);
        $this->assertEqualsWithDelta(7000000, $this->sumar($filas, $rubro, '20RB', 'pagos'), 0.001);
    }

    public function test_rechaza_el_archivo_si_falta_el_grupo_de_compromisos(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pasiva').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->celdas([0 => 'IDENTIFICACIÓN PRESUPUESTAL', 1 => 'CONCEPTO', 2 => 'INICIAL', 11 => 'DEFINITIVA'])));
        $writer->addRow(Row::fromValues($this->celdas([12 => 'Acumulado', 13 => 'Periodo', 14 => 'Acumulado', 15 => 'Periodo'])));
        $writer->addRow(Row::fromValues($this->celdas([
            0 => '0301 - 2.3.45.4503.1000.001.2.3.2.02.02.008 - 20',
            1 => 'Servicio',
            2 => 1000,
            11 => 1000,
            12 => 10,
            14 => 400,
        ])));
        $writer->close();

        try {
            (new LectorPasiva)->leer($path, 'xlsx');
            $this->fail('La pasiva sin la columna de compromisos debió rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('COMPROMISOS', $exception->errors()['archivo'][0]);
        }
    }

    /**
     * @param  list<array{fila: int, valores: array<string, mixed>}>  $filas
     * @return array{fila: int, valores: array<string, mixed>}
     */
    private function fila(array $filas, string $identificacion): array
    {
        foreach ($filas as $fila) {
            if (str_contains((string) $fila['valores']['identificacion'], $identificacion)) {
                return $fila;
            }
        }

        $this->fail('No está la línea '.$identificacion);

        return $filas[0];
    }

    /**
     * @param  list<array{fila: int, valores: array<string, mixed>}>  $filas
     */
    private function sumar(array $filas, string $rubro, string $fuente, string $campo): float
    {
        $total = 0.0;

        foreach ($filas as $fila) {
            $identificacion = (string) $fila['valores']['identificacion'];

            if (! str_contains($identificacion, $rubro) || preg_match('/\s-\s'.preg_quote($fuente, '/').'$/', $identificacion) !== 1) {
                continue;
            }

            $total += LectorPasiva::numero($fila['valores'][$campo] ?? null);
        }

        return $total;
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
