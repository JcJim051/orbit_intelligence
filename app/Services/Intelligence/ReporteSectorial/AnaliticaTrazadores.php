<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Models\FuenteFinanciacion;
use App\Models\ReporteProyecto;
use App\Models\ReporteProyectoTrazadorDistribucion;
use App\Models\Seguimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnaliticaTrazadores
{
    /** @param array<string, mixed> $filtros @param list<int>|null $dependenciasVisibles */
    public function construir(Seguimiento $seguimiento, array $filtros, ?array $dependenciasVisibles): array
    {
        $filas = ReporteProyectoTrazadorDistribucion::query()
            ->whereHas('reporte', fn ($query) => $query->where('seguimiento_id', $seguimiento->id)
                ->when(is_array($dependenciasVisibles), fn ($q) => $q->whereIn('dependencia_id', $dependenciasVisibles ?: [0])))
            ->with(['reporte.proyecto', 'reporte.dependencia', 'reporte.seguimiento', 'trazador', 'categoria', 'politicaPublica', 'fuente'])
            ->get()
            ->map(fn ($fila): array => [
                'reporte_id' => $fila->reporte_proyecto_id,
                'bpin' => $fila->reporte->proyecto->bpin,
                'proyecto' => $fila->reporte->proyecto->nombre,
                'dependencia_id' => $fila->reporte->dependencia_id,
                'dependencia' => $fila->reporte->dependencia->nombre,
                'trazador_id' => $fila->trazador_id,
                'trazador' => $fila->trazador->etiqueta(),
                'politica_id' => $fila->politica_publica_departamental_id,
                'politica' => $fila->politicaPublica?->nombre ?? 'Sin política pública',
                'categoria_id' => $fila->trazador_categoria_id,
                'categoria' => $fila->categoria->nombre,
                'fuente_id' => $fila->fuente_financiacion_id,
                'fuente' => $fila->fuente->etiqueta(),
                'vigencia' => $seguimiento->vigencia,
                'corte' => $seguimiento->fecha_corte->toDateString(),
                'asignado' => (float) $fila->asignado,
                'comprometido' => (float) $fila->comprometido,
                'obligado' => (float) $fila->obligado,
                'pagado' => (float) $fila->pagado,
            ]);

        $reportes = ReporteProyecto::query()->withoutGlobalScopes()->where('seguimiento_id', $seguimiento->id)
            ->when(is_array($dependenciasVisibles), fn ($query) => $query->whereIn('dependencia_id', $dependenciasVisibles ?: [0]))
            ->with(['proyecto', 'dependencia', 'seguimiento'])->get();
        $distribuido = $filas->groupBy(fn (array $fila): string => $fila['reporte_id'].'-'.$fila['fuente_id']);
        $programado = DB::table('actividad_programaciones as ap')
            ->join('actividades as a', 'a.id', '=', 'ap.actividad_id')
            ->where('ap.vigencia', $seguimiento->vigencia)
            ->whereIn('a.proyecto_id', $reportes->pluck('proyecto_id')->unique())
            ->whereIn('a.dependencia_id', $reportes->pluck('dependencia_id')->unique())
            ->groupBy('a.proyecto_id', 'a.dependencia_id', 'ap.fuente_financiacion_id')
            ->selectRaw('a.proyecto_id, a.dependencia_id, ap.fuente_financiacion_id, SUM(ap.valor_asignado) asignado')
            ->get()->groupBy(fn ($fila): string => $fila->proyecto_id.'-'.$fila->dependencia_id);
        $ejecucion = DB::table('ejecuciones_financieras')
            ->whereIn('reporte_proyecto_id', $reportes->pluck('id'))
            ->groupBy('reporte_proyecto_id', 'fuente_financiacion_id')
            ->selectRaw('reporte_proyecto_id, fuente_financiacion_id, SUM(comprometido) comprometido, SUM(obligado) obligado, SUM(pagado) pagado')
            ->get()->groupBy('reporte_proyecto_id');
        $fuentes = FuenteFinanciacion::query()->get()->keyBy('id');
        foreach ($reportes as $reporte) {
            $programadoReporte = $programado->get($reporte->proyecto_id.'-'.$reporte->dependencia_id, collect())->keyBy('fuente_financiacion_id');
            $ejecucionReporte = $ejecucion->get($reporte->id, collect())->keyBy('fuente_financiacion_id');
            $totales = $programadoReporte->keys()->merge($ejecucionReporte->keys())->unique()->mapWithKeys(fn ($fuenteId): array => [(int) $fuenteId => [
                'asignado' => (float) ($programadoReporte->get($fuenteId)?->asignado ?? 0),
                'comprometido' => (float) ($ejecucionReporte->get($fuenteId)?->comprometido ?? 0),
                'obligado' => (float) ($ejecucionReporte->get($fuenteId)?->obligado ?? 0),
                'pagado' => (float) ($ejecucionReporte->get($fuenteId)?->pagado ?? 0),
            ]]);
            foreach ($totales as $fuenteId => $total) {
                $asignado = $distribuido->get($reporte->id.'-'.$fuenteId, collect());
                $residual = collect(['asignado', 'comprometido', 'obligado', 'pagado'])->mapWithKeys(fn (string $campo): array => [$campo => round($total[$campo] - (float) $asignado->sum($campo), 2)]);
                if ($residual->every(fn (float $valor): bool => abs($valor) <= 0.01)) {
                    continue;
                }
                $fuente = $fuentes->get($fuenteId);
                $filas->push([
                    'reporte_id' => $reporte->id, 'bpin' => $reporte->proyecto->bpin, 'proyecto' => $reporte->proyecto->nombre,
                    'dependencia_id' => $reporte->dependencia_id, 'dependencia' => $reporte->dependencia->nombre,
                    'trazador_id' => null, 'trazador' => 'Sin trazador', 'politica_id' => null, 'politica' => 'Sin política pública',
                    'categoria_id' => null, 'categoria' => 'Sin categoría', 'fuente_id' => (int) $fuenteId,
                    'fuente' => $fuente?->etiqueta() ?? 'Fuente '.$fuenteId, 'vigencia' => $seguimiento->vigencia,
                    'corte' => $seguimiento->fecha_corte->toDateString(),
                ] + $residual->all());
            }
        }

        $filas = $filas->filter(function (array $fila) use ($filtros): bool {
            if (($filtros['trazador'] ?? null) === 'sin' && $fila['trazador_id'] !== null) {
                return false;
            }
            if (is_numeric($filtros['trazador'] ?? null) && $fila['trazador_id'] !== (int) $filtros['trazador']) {
                return false;
            }
            if (($filtros['politica'] ?? null) === 'sin' && $fila['politica_id'] !== null) {
                return false;
            }
            if (is_numeric($filtros['politica'] ?? null) && $fila['politica_id'] !== (int) $filtros['politica']) {
                return false;
            }
            if (! empty($filtros['categoria']) && $fila['categoria_id'] !== (int) $filtros['categoria']) {
                return false;
            }
            if (! empty($filtros['fuente']) && $fila['fuente_id'] !== (int) $filtros['fuente']) {
                return false;
            }
            if (! empty($filtros['dependencia_trazador']) && $fila['dependencia_id'] !== (int) $filtros['dependencia_trazador']) {
                return false;
            }

            return true;
        })->values();

        $agrupar = fn (string $campo): Collection => $filas->groupBy($campo)->map(fn (Collection $grupo, string|int $nombre): array => [
            'nombre' => (string) $nombre,
            'asignado' => (float) $grupo->sum('asignado'), 'comprometido' => (float) $grupo->sum('comprometido'),
            'obligado' => (float) $grupo->sum('obligado'), 'pagado' => (float) $grupo->sum('pagado'),
        ])->sortByDesc('comprometido')->values();

        return ['filas' => $filas, 'por_trazador' => $agrupar('trazador'), 'por_politica' => $agrupar('politica'), 'por_categoria' => $agrupar('categoria'), 'por_fuente' => $agrupar('fuente'), 'por_dependencia' => $agrupar('dependencia')];
    }
}
