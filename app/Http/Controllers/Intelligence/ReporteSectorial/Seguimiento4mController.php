<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\MetaProducto;
use App\Models\Seguimiento4mMeta;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Seguimiento4mController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isInvestmentReviewer() || $request->user()?->veTodosLosSectores(), 403);

        $vigencia = $request->integer('vigencia') ?: null;
        $query = Seguimiento4mMeta::query()->with(['metaProducto.dependencia'])->orderBy('codigo_meta_producto')->orderBy('vigencia')->orderBy('fila_fuente');
        if ($vigencia) {
            $query->where('vigencia', $vigencia);
        }
        $filas = $query->get();

        $analitica = $this->analitica($filas);
        $filtro = (string) $request->query('filtro', '');
        $dependenciaFiltro = trim((string) $request->query('dependencia', ''));

        return view('intelligence.reporte-sectorial.seguimientos.4m', [
            'filas' => $filas,
            'vigencia' => $vigencia,
            'tab' => $request->query('tab', 'datos'),
            'resumen' => $this->resumen($filas),
            'analitica' => $analitica,
            'detalleAnalitica' => $this->detalleAnalitica($analitica['por_meta'], $filtro, $dependenciaFiltro),
            'filtro' => $filtro,
            'dependenciaFiltro' => $dependenciaFiltro,
            'vigencias' => Seguimiento4mMeta::query()->select('vigencia')->distinct()->orderByDesc('vigencia')->pluck('vigencia'),
            'pendientes' => MetaProducto::query()->whereNotIn('codigo', $filas->pluck('codigo_meta_producto'))->count(),
        ]);
    }

    private function resumen($filas): array
    {
        $rows = $filas->groupBy('vigencia');
        return $rows->map(fn ($items) => [
            'metas' => $items->pluck('codigo_meta_producto')->unique()->count(),
            'programado' => $items->sum(fn ($r) => (float) ($r->programado_fisico ?? 0)),
            'ejecutado' => $items->sum(fn ($r) => (float) ($r->ejecutado_fisico ?? 0)),
            'asignado' => $items->sum(fn ($r) => (float) ($r->asignado ?? 0)),
            'comprometido' => $items->sum(fn ($r) => (float) ($r->comprometido ?? 0)),
            'obligado_reportado' => $items->whereNotNull('obligado')->sum(fn ($r) => (float) $r->obligado),
            'pagado_reportado' => $items->whereNotNull('pagado')->sum(fn ($r) => (float) $r->pagado),
        ])->all();
    }

    private function analitica($filas): array
    {
        $vigencia = (int) ($filas->max('vigencia') ?: 2026);
        $porMeta = $filas->where('vigencia', $vigencia)->groupBy('codigo_meta_producto')->map(function ($items): array {
            $programado = $items->sum(fn ($row) => (float) ($row->programado_fisico ?? 0));
            $ejecutado = $items->sum(fn ($row) => (float) ($row->ejecutado_fisico ?? 0));
            return [
                'codigo' => $items->first()->codigo_meta_producto,
                'nombre' => $items->first()->metaProducto?->nombre ?? 'Meta histórica sin vínculo',
                'dependencia' => $items->first()->metaProducto?->dependencia?->nombre ?? $items->first()->responsable ?? 'Sin dependencia',
                'programado' => $programado,
                'ejecutado' => $ejecutado,
                'porcentaje' => $programado > 0 ? min(100, $ejecutado / $programado * 100) : null,
                'comprometido' => $items->sum(fn ($row) => (float) ($row->comprometido ?? 0)),
                'motivo' => $this->motivo($programado, $ejecutado, $items->sum(fn ($row) => (float) ($row->comprometido ?? 0))),
            ];
        })->values();

        $evaluables = $porMeta->filter(fn (array $row) => $row['porcentaje'] !== null);
        $porDependencia = $evaluables->groupBy('dependencia')->map(fn ($items, $dependencia) => [
            'nombre' => $dependencia,
            'metas' => $items->count(),
            'promedio' => $items->avg('porcentaje'),
        ])->sortBy('promedio')->values()->take(10)->all();

        return [
            'vigencia' => $vigencia,
            'total' => $porMeta->count(),
            'sin_programacion' => $porMeta->filter(fn (array $row) => $row['programado'] <= 0)->count(),
            'en_cero' => $evaluables->filter(fn (array $row) => $row['ejecutado'] <= 0)->count(),
            'dificiles' => $evaluables->filter(fn (array $row) => $row['porcentaje'] > 0 && $row['porcentaje'] < 30)->count(),
            'cumplidas' => $evaluables->filter(fn (array $row) => $row['porcentaje'] >= 80)->count(),
            'promedio' => $evaluables->avg('porcentaje') ?: 0,
            'criticas' => $evaluables->sortBy('porcentaje')->take(15)->all(),
            'por_dependencia' => $porDependencia,
            'por_meta' => $porMeta->all(),
        ];
    }

    private function motivo(float $programado, float $ejecutado, float $comprometido): string
    {
        if ($programado <= 0) return 'Sin programación';
        if ($ejecutado <= 0 && $comprometido <= 0) return 'Sin ejecución física ni financiera';
        if ($ejecutado <= 0 && $comprometido > 0) return 'Sin ejecución física con inversión';
        $porcentaje = min(100, $ejecutado / $programado * 100);
        if ($porcentaje < 30 && $comprometido > 0) return 'Avance físico bajo con inversión';
        if ($porcentaje < 30) return 'Avance físico bajo';
        if ($porcentaje < 80) return 'Avance en riesgo';
        return 'Avance adecuado';
    }

    private function detalleAnalitica(array $metas, string $filtro, string $dependencia): array
    {
        return collect($metas)->filter(function (array $row) use ($filtro, $dependencia): bool {
            if ($dependencia !== '' && $row['dependencia'] !== $dependencia) {
                return false;
            }

            return match ($filtro) {
                'cero' => $row['porcentaje'] !== null && $row['ejecutado'] <= 0,
                'dificiles' => $row['porcentaje'] !== null && $row['porcentaje'] > 0 && $row['porcentaje'] < 30,
                'cumplidas' => $row['porcentaje'] !== null && $row['porcentaje'] >= 80,
                'sin_programacion' => $row['porcentaje'] === null,
                default => true,
            };
        })->sortBy('porcentaje')->values()->all();
    }
}
