<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoReporteProyecto;
use App\Enums\GrupoFuenteFinanciacion;
use App\Models\ActividadProgramacion;
use App\Models\AvanceFisico;
use App\Models\EjecucionFinanciera;
use App\Models\PasivaLinea;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\Techo;

/**
 * Datos de la pantalla "Reporte mensual de metas": un renglón por BPIN × dependencia visible
 * con techo, reportado y saldo por grupo de fuente (Recursos propios, SGR y las demás que existan).
 * Todas las consultas pasan por el scope sectorial.
 */
class ResumenSectorial
{
    /**
     * @return array{filas: list<array<string, mixed>>, grupos: list<GrupoFuenteFinanciacion>}
     */
    public function construir(Seguimiento $seguimiento, ?int $dependenciaId = null, ?string $buscar = null): array
    {
        $techos = Techo::query()
            ->delSeguimiento($seguimiento)
            ->when($dependenciaId, fn ($query) => $query->where('dependencia_id', $dependenciaId))
            ->when($buscar, fn ($query) => $query->whereHas('proyecto', fn ($proyecto) => $proyecto->where('bpin', 'like', '%'.$buscar.'%')->orWhere('nombre', 'like', '%'.$buscar.'%')))
            ->with(['proyecto', 'fuente', 'dependencia'])
            ->get();

        $reportes = ReporteProyecto::query()
            ->delSeguimiento($seguimiento)
            ->withSum('focalizaciones', 'porcentaje')
            ->get()
            ->keyBy(fn (ReporteProyecto $reporte): string => $reporte->proyecto_id.'-'.$reporte->dependencia_id);

        $reporteIds = $reportes->pluck('id');

        $ejecutado = EjecucionFinanciera::query()
            ->whereIn('reporte_proyecto_id', $reporteIds)
            ->groupBy('reporte_proyecto_id', 'fuente_financiacion_id')
            ->selectRaw('reporte_proyecto_id, fuente_financiacion_id, SUM(comprometido) as comprometido, SUM(pagado) as pagado')
            ->get()
            ->keyBy(fn ($fila): string => $fila->reporte_proyecto_id.'-'.$fila->fuente_financiacion_id);

        $sinEvidencia = AvanceFisico::query()
            ->whereIn('reporte_proyecto_id', $reporteIds)
            ->pendientesEvidencia()
            ->with('actividad')
            ->get()
            ->groupBy('reporte_proyecto_id');

        $programado = ActividadProgramacion::query()
            ->where('vigencia', $seguimiento->vigencia)
            ->whereHas('actividad', fn ($actividad) => $actividad
                ->whereIn('proyecto_id', $techos->pluck('proyecto_id')->unique())
                ->whereIn('dependencia_id', $techos->pluck('dependencia_id')->unique()))
            ->with('actividad:id,proyecto_id,dependencia_id')
            ->get()
            ->groupBy(fn (ActividadProgramacion $programacion): string => $programacion->actividad->proyecto_id.'-'.$programacion->actividad->dependencia_id)
            ->map(fn ($programaciones): float => (float) $programaciones->sum('valor_asignado'));

        $lineas = PasivaLinea::query()
            ->where('seguimiento_id', $seguimiento->id)
            ->vigentes()
            ->whereIn('proyecto_id', $techos->pluck('proyecto_id')->unique())
            ->whereIn('dependencia_id', $techos->pluck('dependencia_id')->unique())
            ->with('fuente')
            ->orderBy('fila')
            ->get()
            ->groupBy(fn (PasivaLinea $linea): string => $linea->proyecto_id.'-'.$linea->dependencia_id);

        $grupos = [GrupoFuenteFinanciacion::RecursosPropios->value => GrupoFuenteFinanciacion::RecursosPropios, GrupoFuenteFinanciacion::Sgr->value => GrupoFuenteFinanciacion::Sgr];
        $filas = [];

        foreach ($techos->groupBy(fn (Techo $techo): string => $techo->proyecto_id.'-'.$techo->dependencia_id) as $llave => $techosProyecto) {
            /** @var Techo $primero */
            $primero = $techosProyecto->first();
            $reporte = $reportes->get($llave);
            $porGrupo = [];
            $total = ['techo' => 0.0, 'reportado' => 0.0, 'saldo' => 0.0];

            foreach ($techosProyecto as $techo) {
                $grupo = $techo->fuente->grupo();
                $grupos[$grupo->value] = $grupo;
                $reportado = $reporte ? (float) ($ejecutado->get($reporte->id.'-'.$techo->fuente_financiacion_id)?->comprometido ?? 0) : 0.0;
                $valor = (float) $techo->valor;

                $porGrupo[$grupo->value] ??= ['techo' => 0.0, 'reportado' => 0.0, 'saldo' => 0.0, 'fuentes' => []];
                $porGrupo[$grupo->value]['techo'] += $valor;
                $porGrupo[$grupo->value]['reportado'] += $reportado;
                $porGrupo[$grupo->value]['saldo'] += $valor - $reportado;
                $porGrupo[$grupo->value]['fuentes'][] = ['etiqueta' => $techo->fuente->etiqueta(), 'techo' => $valor, 'reportado' => $reportado, 'saldo' => $valor - $reportado, 'techo_id' => $techo->id, 'ajustado' => $techo->valor_ajuste !== null];

                $total['techo'] += $valor;
                $total['reportado'] += $reportado;
                $total['saldo'] += $valor - $reportado;
            }

            $requiereFocalizacion = $primero->proyecto->requiereFocalizacionMensual();
            $sumaFocalizacion = (float) ($reporte?->focalizaciones_sum_porcentaje ?? 0);

            $filas[] = [
                'proyecto' => $primero->proyecto,
                'dependencia' => $primero->dependencia,
                'reporte' => $reporte,
                'estado' => $reporte?->estado ?? EstadoReporteProyecto::Borrador,
                'grupos' => $porGrupo,
                'total' => $total,
                'valor_programado' => $programado->get($llave, 0.0),
                'lineas' => $lineas->get($llave, collect()),
                'actividades_sin_evidencia' => $reporte ? $sinEvidencia->get($reporte->id, collect())->map(fn (AvanceFisico $avance): string => $avance->actividad->etiqueta())->values() : collect(),
                'requiere_focalizacion' => $requiereFocalizacion,
                'focalizacion_pendiente' => $requiereFocalizacion && abs($sumaFocalizacion - 100) > (float) config('reporte_sectorial.tolerancia_focalizacion_porcentaje', 0.01),
            ];
        }

        usort($filas, fn (array $a, array $b): int => [$a['dependencia']->nombre, $a['proyecto']->bpin] <=> [$b['dependencia']->nombre, $b['proyecto']->bpin]);
        uasort($grupos, fn (GrupoFuenteFinanciacion $a, GrupoFuenteFinanciacion $b): int => $a->orden() <=> $b->orden());

        return ['filas' => $filas, 'grupos' => array_values($grupos)];
    }
}
