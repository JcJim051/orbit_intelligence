<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\Seguimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CronogramaReporteIndicadores
{
    private const ESTADOS_HABILITADOS = ['historico_validado', 'listo_habilitar', 'activo'];

    /**
     * @return Collection<int, array{indicador: IndicadorTerritorial, periodo: string, periodo_inicio: Carbon, periodo_fin: Carbon, obligatorio: bool, oportunidad: bool, dato: ?IndicadorTerritorialDato}>
     */
    public function paraDependencia(Seguimiento $seguimiento, int $dependenciaId): Collection
    {
        $indicadores = IndicadorTerritorial::query()
            ->where('dependencia_id', $dependenciaId)
            ->whereIn('estado', self::ESTADOS_HABILITADOS)
            ->whereNotNull('periodicidad')
            // Un periodo previamente incorporado y validado en la reconstrucción
            // histórica también satisface el cronograma; nunca se duplica la serie.
            ->with('datos')
            ->orderBy('codigo')
            ->get();

        return $indicadores
            ->map(function (IndicadorTerritorial $indicador) use ($seguimiento): ?array {
                $periodo = $this->periodoExigible($indicador->periodicidad, $seguimiento);
                if ($periodo === null) {
                    return null;
                }

                $dato = $indicador->datos->firstWhere('periodo', $periodo['periodo']);

                return [
                    'indicador' => $indicador,
                    ...$periodo,
                    'dato' => $dato,
                ];
            })
            ->filter()
            ->values();
    }

    public function pendientesPreparacion(int $dependenciaId): Collection
    {
        return IndicadorTerritorial::query()
            ->where('dependencia_id', $dependenciaId)
            ->whereNotIn('estado', self::ESTADOS_HABILITADOS)
            ->orderBy('codigo')
            ->get();
    }

    public function pendientesObligatorios(Seguimiento $seguimiento, int $dependenciaId): Collection
    {
        return $this->paraDependencia($seguimiento, $dependenciaId)
            ->filter(fn (array $fila): bool => $fila['obligatorio'] && $fila['dato'] === null)
            ->values();
    }

    /**
     * El cierre institucional exige dos cosas: que todos los obligatorios se
     * hayan entregado y que todo dato periódico entregado haya sido aprobado.
     *
     * @return Collection<int, string>
     */
    public function pendientesParaCerrar(Seguimiento $seguimiento): Collection
    {
        $dependenciaIds = IndicadorTerritorial::query()
            ->whereIn('estado', self::ESTADOS_HABILITADOS)
            ->whereNotNull('dependencia_id')
            ->distinct()
            ->pluck('dependencia_id');

        return $dependenciaIds->flatMap(function (int $dependenciaId) use ($seguimiento): Collection {
            return $this->paraDependencia($seguimiento, $dependenciaId)
                ->map(function (array $fila): ?string {
                    $indicador = $fila['indicador'];
                    $dato = $fila['dato'];

                    if ($fila['obligatorio'] && $dato === null) {
                        return "Falta reportar {$indicador->codigo} — {$indicador->nombre} para {$fila['periodo']}.";
                    }

                    if ($dato !== null && $dato->estado !== IndicadorTerritorialDato::ESTADO_APROBADO) {
                        $estado = IndicadorTerritorialDato::ESTADOS[$dato->estado] ?? $dato->estado;

                        return "El indicador {$indicador->codigo} — {$indicador->nombre} para {$fila['periodo']} continúa en estado {$estado}.";
                    }

                    return null;
                })
                ->filter();
        })->values();
    }

    /** @return array{periodo: string, periodo_inicio: Carbon, periodo_fin: Carbon, obligatorio: bool, oportunidad: bool}|null */
    public function periodoExigible(?string $periodicidad, Seguimiento $seguimiento): ?array
    {
        $corte = Carbon::create($seguimiento->vigencia, $seguimiento->mes, 1)->startOfMonth();
        $inicio = null;
        $fin = null;
        $obligatorio = true;

        if (in_array($periodicidad, ['diaria', 'semanal', 'mensual'], true)) {
            $inicio = $corte->copy()->subMonthNoOverflow()->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();
        } elseif ($periodicidad === 'trimestral' && in_array($corte->month, [1, 4, 7, 10], true)) {
            $fin = $corte->copy()->subMonth()->endOfMonth();
            $inicio = $fin->copy()->subMonths(2)->startOfMonth();
        } elseif ($periodicidad === 'cuatrimestral' && in_array($corte->month, [1, 5, 9], true)) {
            $fin = $corte->copy()->subMonth()->endOfMonth();
            $inicio = $fin->copy()->subMonths(3)->startOfMonth();
        } elseif ($periodicidad === 'semestral') {
            if (in_array($corte->month, [1, 2, 3], true)) {
                $inicio = Carbon::create($corte->year - 1, 7, 1);
                $fin = Carbon::create($corte->year - 1, 12, 31);
                $obligatorio = $corte->month === 3;
            } elseif (in_array($corte->month, [7, 8], true)) {
                $inicio = Carbon::create($corte->year, 1, 1);
                $fin = Carbon::create($corte->year, 6, 30);
                $obligatorio = $corte->month === 8;
            }
        } elseif ($periodicidad === 'anual' && in_array($corte->month, [1, 2, 3], true)) {
            $inicio = Carbon::create($corte->year - 1, 1, 1);
            $fin = Carbon::create($corte->year - 1, 12, 31);
            $obligatorio = $corte->month === 3;
        }

        if ($inicio === null || $fin === null) {
            return null;
        }

        return [
            'periodo' => $this->etiquetaPeriodo($periodicidad, $inicio, $fin),
            'periodo_inicio' => $inicio,
            'periodo_fin' => $fin,
            'obligatorio' => $obligatorio,
            'oportunidad' => ! $obligatorio,
        ];
    }

    private function etiquetaPeriodo(?string $periodicidad, Carbon $inicio, Carbon $fin): string
    {
        if (in_array($periodicidad, ['diaria', 'semanal', 'mensual'], true)) {
            return ucfirst($inicio->locale('es')->translatedFormat('F Y'));
        }

        if ($periodicidad === 'anual') {
            return (string) $inicio->year;
        }

        return $inicio->format('d/m/Y').' - '.$fin->format('d/m/Y');
    }
}
