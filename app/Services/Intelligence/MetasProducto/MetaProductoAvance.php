<?php

namespace App\Services\Intelligence\MetasProducto;

use App\Models\MetaProducto;
use App\Models\Seguimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MetaProductoAvance
{
    /**
     * @return array<string, mixed>|null
     */
    public function resumenActual(MetaProducto $meta): ?array
    {
        $seguimiento = $this->ultimoSeguimientoConDatos($meta);

        return $seguimiento === null ? null : $this->resumenParaSeguimiento($meta, $seguimiento);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function historico(MetaProducto $meta): Collection
    {
        return $this->seguimientosConDatos($meta)
            ->map(fn (Seguimiento $seguimiento): array => $this->resumenParaSeguimiento($meta, $seguimiento))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenParaSeguimiento(MetaProducto $meta, Seguimiento $seguimiento): array
    {
        $actividadIds = $this->actividadIds($meta, $seguimiento);

        $programado = $actividadIds->isEmpty()
            ? 0.0
            : (float) DB::table('actividades')
                ->whereIn('id', $actividadIds)
                ->sum('cantidad_programada');

        $avance = (float) DB::table('avances_fisicos')
            ->join('actividades', 'actividades.id', '=', 'avances_fisicos.actividad_id')
            ->join('reportes_proyecto', 'reportes_proyecto.id', '=', 'avances_fisicos.reporte_proyecto_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->sum('avances_fisicos.cantidad');

        $asignado = $actividadIds->isEmpty()
            ? 0.0
            : (float) DB::table('actividad_programaciones')
                ->whereIn('actividad_id', $actividadIds)
                ->where('vigencia', $seguimiento->vigencia)
                ->sum('valor_asignado');

        $financiero = DB::table('ejecuciones_financieras')
            ->join('actividades', 'actividades.id', '=', 'ejecuciones_financieras.actividad_id')
            ->join('reportes_proyecto', 'reportes_proyecto.id', '=', 'ejecuciones_financieras.reporte_proyecto_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->selectRaw('
                COALESCE(SUM(ejecuciones_financieras.comprometido), 0) as comprometido,
                COALESCE(SUM(ejecuciones_financieras.obligado), 0) as obligado,
                COALESCE(SUM(ejecuciones_financieras.pagado), 0) as pagado
            ')
            ->first();

        $proyectos = (int) DB::table('reportes_proyecto')
            ->join('actividades', function ($join): void {
                $join->on('actividades.proyecto_id', '=', 'reportes_proyecto.proyecto_id')
                    ->on('actividades.dependencia_id', '=', 'reportes_proyecto.dependencia_id');
            })
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->distinct('reportes_proyecto.proyecto_id')
            ->count('reportes_proyecto.proyecto_id');

        $dependencias = (int) DB::table('reportes_proyecto')
            ->join('actividades', function ($join): void {
                $join->on('actividades.proyecto_id', '=', 'reportes_proyecto.proyecto_id')
                    ->on('actividades.dependencia_id', '=', 'reportes_proyecto.dependencia_id');
            })
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->distinct('reportes_proyecto.dependencia_id')
            ->count('reportes_proyecto.dependencia_id');

        $comprometido = (float) ($financiero->comprometido ?? 0);
        $obligado = (float) ($financiero->obligado ?? 0);
        $pagado = (float) ($financiero->pagado ?? 0);

        return [
            'seguimiento_id' => $seguimiento->id,
            'seguimiento' => $seguimiento->etiqueta(),
            'fecha_corte' => $seguimiento->fecha_corte?->toDateString(),
            'programado_fisico' => $programado,
            'avance_fisico' => $avance,
            'porcentaje_fisico' => $programado > 0 ? ($avance / $programado) * 100 : 0.0,
            'asignado' => $asignado,
            'comprometido' => $comprometido,
            'obligado' => $obligado,
            'pagado' => $pagado,
            'porcentaje_financiero' => $asignado > 0 ? ($comprometido / $asignado) * 100 : 0.0,
            'proyectos' => $proyectos,
            'dependencias' => $dependencias,
        ];
    }

    private function ultimoSeguimientoConDatos(MetaProducto $meta): ?Seguimiento
    {
        return $this->seguimientosConDatos($meta)->first();
    }

    /**
     * @return Collection<int, Seguimiento>
     */
    private function seguimientosConDatos(MetaProducto $meta): Collection
    {
        $idsConAvance = DB::table('seguimientos')
            ->join('reportes_proyecto', 'reportes_proyecto.seguimiento_id', '=', 'seguimientos.id')
            ->join('avances_fisicos', 'avances_fisicos.reporte_proyecto_id', '=', 'reportes_proyecto.id')
            ->join('actividades', 'actividades.id', '=', 'avances_fisicos.actividad_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->select('seguimientos.id');

        $idsConEjecucion = DB::table('seguimientos')
            ->join('reportes_proyecto', 'reportes_proyecto.seguimiento_id', '=', 'seguimientos.id')
            ->join('ejecuciones_financieras', 'ejecuciones_financieras.reporte_proyecto_id', '=', 'reportes_proyecto.id')
            ->join('actividades', 'actividades.id', '=', 'ejecuciones_financieras.actividad_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->select('seguimientos.id');

        $ids = DB::query()
            ->fromSub($idsConAvance->union($idsConEjecucion), 'seguimientos_con_datos')
            ->pluck('id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Seguimiento::query()
            ->whereIn('id', $ids)
            ->orderByDesc('vigencia')
            ->orderByDesc('mes')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function actividadIds(MetaProducto $meta, Seguimiento $seguimiento): Collection
    {
        $idsConAvance = DB::table('actividades')
            ->join('avances_fisicos', 'avances_fisicos.actividad_id', '=', 'actividades.id')
            ->join('reportes_proyecto', 'reportes_proyecto.id', '=', 'avances_fisicos.reporte_proyecto_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->select('actividades.id');

        $idsConEjecucion = DB::table('actividades')
            ->join('ejecuciones_financieras', 'ejecuciones_financieras.actividad_id', '=', 'actividades.id')
            ->join('reportes_proyecto', 'reportes_proyecto.id', '=', 'ejecuciones_financieras.reporte_proyecto_id')
            ->where('actividades.meta_producto_id', $meta->id)
            ->where('reportes_proyecto.seguimiento_id', $seguimiento->id)
            ->select('actividades.id');

        return DB::query()
            ->fromSub($idsConAvance->union($idsConEjecucion), 'actividades_con_datos')
            ->pluck('id')
            ->unique()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }
}
