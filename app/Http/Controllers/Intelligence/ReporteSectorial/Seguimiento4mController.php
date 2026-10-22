<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Seguimiento4mMeta;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class Seguimiento4mController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isInvestmentReviewer() || $request->user()?->veTodosLosSectores(), 403);

        $vigencia = $request->integer('vigencia') ?: null;
        $tab = in_array($request->query('tab'), ['datos', 'analitica'], true)
            ? (string) $request->query('tab')
            : 'datos';
        $buscar = trim((string) $request->query('buscar', ''));
        $vigencias = Seguimiento4mMeta::query()
            ->select('vigencia')
            ->distinct()
            ->orderByDesc('vigencia')
            ->pluck('vigencia');

        if ($tab === 'datos') {
            $filasQuery = Seguimiento4mMeta::query()
                ->with([
                    'metaProducto:id,codigo,nombre,dependencia_id',
                    'metaProducto.dependencia:id,nombre',
                ])
                ->when($vigencia, fn ($query) => $query->where('vigencia', $vigencia))
                ->when($buscar !== '', function ($query) use ($buscar): void {
                    $termino = '%'.mb_strtolower($buscar).'%';
                    $query->where(function ($query) use ($termino): void {
                        $query
                            ->whereRaw('LOWER(codigo_meta_producto) LIKE ?', [$termino])
                            ->orWhereRaw('LOWER(responsable) LIKE ?', [$termino])
                            ->orWhereHas('metaProducto', function ($query) use ($termino): void {
                                $query
                                    ->whereRaw('LOWER(nombre) LIKE ?', [$termino])
                                    ->orWhereHas('dependencia', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', [$termino]));
                            });
                    });
                })
                ->orderBy('codigo_meta_producto')
                ->orderBy('vigencia')
                ->orderBy('fila_fuente');

            $filas = $filasQuery->paginate(50)->withQueryString();

            return view('intelligence.reporte-sectorial.seguimientos.4m', [
                'filas' => $filas,
                'vigencia' => $vigencia,
                'tab' => $tab,
                'buscar' => $buscar,
                'resumen' => [],
                'analitica' => [],
                'detalleAnalitica' => collect(),
                'filtro' => '',
                'dependenciaFiltro' => '',
                'vigencias' => $vigencias,
            ]);
        }

        $todasLasFilas = Seguimiento4mMeta::query()
            ->with([
                'metaProducto.dependencia',
                'metaProducto.indicadorProducto',
                'metaProducto.planIndicativo',
            ])
            ->orderBy('codigo_meta_producto')
            ->orderBy('vigencia')
            ->orderBy('fila_fuente')
            ->get();
        $filas = $vigencia
            ? $todasLasFilas->where('vigencia', $vigencia)->values()
            : $todasLasFilas;
        $vigenciaCorte = $vigencia ?: (int) ($todasLasFilas->max('vigencia') ?: now()->year);
        $filasHastaElCorte = $todasLasFilas->where('vigencia', '<=', $vigenciaCorte);

        $analitica = $this->analitica($filasHastaElCorte, $vigenciaCorte);
        $filtro = (string) $request->query('filtro', '');
        $dependenciaFiltro = trim((string) $request->query('dependencia', ''));
        $detalle = collect($this->detalleAnalitica($analitica['por_meta'], $filtro, $dependenciaFiltro));
        if ($buscar !== '') {
            $termino = Str::of($buscar)->ascii()->lower()->toString();
            $detalle = $detalle->filter(function (array $row) use ($termino): bool {
                $texto = Str::of(implode(' ', [$row['codigo'], $row['nombre'], $row['dependencia'], $row['motivo']]))
                    ->ascii()
                    ->lower()
                    ->toString();

                return str_contains($texto, $termino);
            })->values();
        }
        $paginaDetalle = max(1, $request->integer('pagina_detalle', 1));
        $detalleAnalitica = new LengthAwarePaginator(
            $detalle->forPage($paginaDetalle, 50)->values(),
            $detalle->count(),
            50,
            $paginaDetalle,
            ['path' => $request->url(), 'pageName' => 'pagina_detalle'],
        );
        $detalleAnalitica->appends($request->except('pagina_detalle'))->fragment('detalle-analitica');

        return view('intelligence.reporte-sectorial.seguimientos.4m', [
            'filas' => $filas,
            'vigencia' => $vigencia,
            'tab' => $tab,
            'buscar' => $buscar,
            'resumen' => $this->resumen($filas),
            'analitica' => $analitica,
            'detalleAnalitica' => $detalleAnalitica,
            'filtro' => $filtro,
            'dependenciaFiltro' => $dependenciaFiltro,
            'vigencias' => $vigencias,
        ]);
    }

    private function resumen(Collection $filas): array
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

    private function analitica(Collection $filas, int $vigenciaCorte): array
    {
        $porMeta = $filas->groupBy('codigo_meta_producto')->map(function (Collection $items) use ($vigenciaCorte): array {
            $metaProducto = $items->first()->metaProducto;
            $tipoAcumulacion = $this->tipoAcumulacion(
                $metaProducto?->indicadorProducto?->tipo_acumulacion,
                $items->first()->orientacion,
            );
            $valoresPorVigencia = $items->groupBy('vigencia')->map(fn (Collection $filasVigencia): array => [
                'programado' => $filasVigencia->sum(fn (Seguimiento4mMeta $row): float => (float) ($row->programado_fisico ?? 0)),
                'ejecutado' => $filasVigencia->sum(fn (Seguimiento4mMeta $row): float => (float) ($row->ejecutado_fisico ?? 0)),
            ]);
            $ejecutado = $this->consolidarValores($valoresPorVigencia->pluck('ejecutado'), $tipoAcumulacion);
            $planIndicativo = $metaProducto?->planIndicativo
                ?->whereBetween('vigencia', [2024, 2027])
                ->sortBy('vigencia');
            $programadoHistorico = $valoresPorVigencia->map(
                fn (array $valores): float => $valores['programado'],
            );
            $programadoPlan = $planIndicativo
                ?->keyBy('vigencia')
                ->map(fn ($fila): float => (float) $fila->valor_programado)
                ?? collect();
            $programacionPorVigencia = collect(range(2024, 2027))->mapWithKeys(
                function (int $vigencia) use ($programadoPlan, $programadoHistorico): array {
                    if ($programadoPlan->has($vigencia)) {
                        return [$vigencia => $programadoPlan->get($vigencia)];
                    }

                    if ($programadoHistorico->has($vigencia)) {
                        return [$vigencia => $programadoHistorico->get($vigencia)];
                    }

                    return [];
                },
            );
            $metaCuatrienioRegistrada = $metaProducto?->indicadorProducto?->meta_cuatrienio;
            $metaCuatrienio = $metaCuatrienioRegistrada !== null ? (float) $metaCuatrienioRegistrada : null;
            if ($metaCuatrienio === null && $programadoPlan->isNotEmpty()) {
                $metaDesdePlan = $this->consolidarValores($programacionPorVigencia, $tipoAcumulacion);
                $metaCuatrienio = $metaDesdePlan > 0 || $tipoAcumulacion === 'decreciente' ? $metaDesdePlan : null;
            }
            if ($metaCuatrienio !== null && $metaCuatrienio <= 0 && $tipoAcumulacion !== 'decreciente') {
                $metaCuatrienio = null;
            }
            $programadoAlCorte = $this->consolidarValores(
                $programacionPorVigencia->filter(
                    fn (mixed $valor, int $vigencia): bool => $vigencia <= $vigenciaCorte,
                ),
                $tipoAcumulacion,
            );
            $lineaBase = $metaProducto?->indicadorProducto?->linea_base;
            $porcentaje = $this->porcentajeAvance(
                $metaCuatrienio,
                $ejecutado,
                $tipoAcumulacion,
                $lineaBase !== null ? (float) $lineaBase : null,
            );
            $cumplimientoAlCorte = $this->porcentajeAvance(
                $programadoAlCorte > 0 || $tipoAcumulacion === 'decreciente' ? $programadoAlCorte : null,
                $ejecutado,
                $tipoAcumulacion,
                $lineaBase !== null ? (float) $lineaBase : null,
            );
            $comprometido = $items->sum(fn (Seguimiento4mMeta $row): float => (float) ($row->comprometido ?? 0));

            return [
                'codigo' => $items->first()->codigo_meta_producto,
                'nombre' => $metaProducto?->nombre ?? 'Meta histórica sin vínculo',
                'dependencia' => $metaProducto?->dependencia?->nombre ?? $items->first()->responsable ?? 'Sin dependencia',
                'tipo_acumulacion' => $tipoAcumulacion,
                'programado' => $metaCuatrienio,
                'programado_al_corte' => $programadoAlCorte,
                'ejecutado' => $ejecutado,
                'porcentaje' => $porcentaje,
                'cumplimiento_al_corte' => $cumplimientoAlCorte,
                'comprometido' => $comprometido,
                'motivo' => $this->motivo($metaCuatrienio, $ejecutado, $comprometido, $porcentaje),
            ];
        })->values();

        $evaluables = $porMeta->filter(fn (array $row) => $row['porcentaje'] !== null);
        $porDependencia = $porMeta->groupBy('dependencia')->map(function (Collection $items, string $dependencia): array {
            $metasEvaluables = $items->filter(fn (array $row): bool => $row['porcentaje'] !== null);

            return [
                'nombre' => $dependencia,
                'metas' => $items->count(),
                'metas_evaluables' => $metasEvaluables->count(),
                'promedio' => $metasEvaluables->isNotEmpty() ? (float) $metasEvaluables->avg('porcentaje') : null,
            ];
        })->sortBy(fn (array $dependencia): float => $dependencia['promedio'] ?? PHP_FLOAT_MAX)->values()->all();

        return [
            'vigencia' => $vigenciaCorte,
            'total' => $porMeta->count(),
            'sin_programacion' => $porMeta->filter(fn (array $row) => $row['programado'] === null)->count(),
            'en_cero' => $evaluables->filter(fn (array $row) => $row['ejecutado'] <= 0)->count(),
            'dificiles' => $evaluables->filter(fn (array $row) => $row['porcentaje'] > 0 && $row['porcentaje'] < 30)->count(),
            'cumplidas' => $evaluables->filter(fn (array $row) => $row['porcentaje'] >= 80)->count(),
            'promedio' => $evaluables->avg('porcentaje') ?: 0,
            'criticas' => $evaluables->sortBy('porcentaje')->take(15)->all(),
            'por_dependencia' => $porDependencia,
            'por_meta' => $porMeta->all(),
        ];
    }

    private function motivo(?float $programado, float $ejecutado, float $comprometido, ?float $porcentaje): string
    {
        if ($programado === null || $porcentaje === null) {
            return 'Sin meta cuatrienal';
        }
        if ($porcentaje >= 80) {
            return 'Avance adecuado';
        }
        if ($ejecutado <= 0 && $comprometido <= 0) {
            return 'Sin ejecución física ni financiera';
        }
        if ($ejecutado <= 0 && $comprometido > 0) {
            return 'Sin ejecución física con inversión';
        }
        if ($porcentaje < 30 && $comprometido > 0) {
            return 'Avance físico bajo con inversión';
        }
        if ($porcentaje < 30) {
            return 'Avance físico bajo';
        }
        return 'Avance en riesgo';
    }

    private function tipoAcumulacion(?string $tipoIndicador, ?string $orientacionHistorica): string
    {
        $tipo = Str::of($tipoIndicador ?: $orientacionHistorica ?: 'suma')
            ->ascii()
            ->lower()
            ->replace(' ', '_')
            ->toString();

        return in_array($tipo, ['suma', 'constante', 'creciente', 'decreciente', 'no_acumulable'], true)
            ? $tipo
            : 'suma';
    }

    private function consolidarValores(Collection $valores, string $tipoAcumulacion): float
    {
        $numeros = $valores
            ->filter(fn (mixed $valor): bool => $valor !== null && $valor !== '')
            ->map(fn (mixed $valor): float => (float) $valor);

        if ($numeros->isEmpty()) {
            return 0.0;
        }

        return $tipoAcumulacion === 'suma'
            ? (float) $numeros->sum()
            : (float) $numeros->last();
    }

    private function porcentajeAvance(
        ?float $meta,
        float $ejecutado,
        string $tipoAcumulacion,
        ?float $lineaBase,
    ): ?float {
        if ($meta === null) {
            return null;
        }

        if ($tipoAcumulacion !== 'decreciente') {
            return $meta > 0 ? min(100, max(0, $ejecutado / $meta * 100)) : null;
        }

        if ($lineaBase === null) {
            return $ejecutado <= $meta ? 100.0 : 0.0;
        }

        $reduccionEsperada = $lineaBase - $meta;
        if (abs($reduccionEsperada) < 0.0000001) {
            return $ejecutado <= $meta ? 100.0 : 0.0;
        }

        return min(100, max(0, ($lineaBase - $ejecutado) / $reduccionEsperada * 100));
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
