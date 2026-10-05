<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Filament\Pages\Workspace;
use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\MetaProducto;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\Techo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeguimientoDependenciaController extends Controller
{
    private const ODS_APPROVED_STATUSES = ['accepted', 'validated', 'confirmed', 'approved'];

    private const DETAIL_RELATIONS = [
        'dependencias',
        'metas_producto',
        'proyectos',
        'proyectos_reportados',
        'techo',
        'comprometido',
        'ejecucion_financiera',
        'obligado_pagado',
        'avance_fisico',
        'programacion_fisica',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seguimiento = $this->seguimientoMasReciente();
        $dependencias = $this->dependenciasVisibles($request)
            ->withCount([
                'proyectos',
            ])
            ->get();
        $dependenciaIds = $dependencias->pluck('id')->all();
        $conteoMetasProducto = $this->conteoMetasProductoPorDependencia($dependenciaIds);
        $conteoProyectos = $this->conteoProyectosPorDependencia($dependenciaIds, $seguimiento);

        $dependencias->each(function (Dependencia $dependencia) use ($conteoMetasProducto, $conteoProyectos): void {
            $dependencia->setAttribute('metas_producto_count', (int) $conteoMetasProducto->get($dependencia->id, 0));
            $dependencia->setAttribute('proyectos_count', (int) $conteoProyectos->get($dependencia->id, $dependencia->proyectos_count ?? 0));
        });

        $metricas = $seguimiento
            ? $this->metricasPorDependencia($seguimiento, $dependenciaIds)
            : collect();

        return view('intelligence.seguimiento-dependencias.index', [
            'seguimiento' => $seguimiento,
            'dependencias' => $dependencias,
            'metricas' => $metricas,
            'filtros' => [
                'q' => trim((string) $request->query('q', '')),
            ],
            'puedeVerTodo' => $user?->veTodosLosSectores() ?? false,
        ]);
    }

    public function show(Request $request, Dependencia $dependencia): View
    {
        $user = $request->user();
        abort_unless($user?->veTodosLosSectores() || $user?->perteneceADependencia($dependencia->id), 403);

        $seguimiento = $this->seguimientoMasReciente();
        $metricas = $seguimiento
            ? $this->metricasPorDependencia($seguimiento, [$dependencia->id])->get($dependencia->id, $this->metricasVacias())
            : $this->metricasVacias();

        $metaProductoIds = $this->metaProductoIdsPorDependencia($dependencia->id);

        $metasResultado = MetaProducto::query()
            ->whereIn('id', $metaProductoIds->isNotEmpty() ? $metaProductoIds : [0])
            ->with([
                'metaResultado.indicador.odsReview.links' => fn ($query) => $query->whereIn('status', self::ODS_APPROVED_STATUSES),
                'metaResultado.indicador.odsReview.links.odsIndicator.target.goal',
                'proyectos',
                'subprograma.programa.linea.eje.pilar',
                'sectorMga',
            ])
            ->get()
            ->groupBy('meta_resultado_id')
            ->map(function (Collection $metasProducto): array {
                $metaResultado = $metasProducto->first()?->metaResultado;
                $ods = $metaResultado?->indicador?->odsReview?->links
                    ?->map(fn (IndicadorResultadoOdsLink $link): array => [
                        'goal' => $link->odsIndicator?->target?->goal,
                        'indicator' => $link->odsIndicator,
                    ])
                    ->filter(fn (array $item): bool => $item['indicator'] !== null)
                    ->unique(fn (array $item): string => (string) $item['indicator']->id)
                    ->values() ?? collect();

                return [
                    'meta_resultado' => $metaResultado,
                    'indicador' => $metaResultado?->indicador,
                    'metas_producto' => $metasProducto->values(),
                    'proyectos' => $metasProducto->flatMap->proyectos->unique('id')->values(),
                    'ods' => $ods,
                ];
            })
            ->values();

        $reportes = $seguimiento
            ? ReporteProyecto::query()
                ->withoutGlobalScopes()
                ->where('seguimiento_id', $seguimiento->id)
                ->where('dependencia_id', $dependencia->id)
                ->with([
                    'proyecto.metasProducto',
                    'avances.actividad.metaProducto',
                    'ejecuciones.actividad.metaProducto',
                    'ejecuciones.fuente',
                ])
                ->orderByDesc('updated_at')
                ->get()
            : collect();
        $actividades = $this->actividadesDeReportes($reportes);

        return view('intelligence.seguimiento-dependencias.show', [
            'dependencia' => $dependencia,
            'seguimiento' => $seguimiento,
            'metricas' => $metricas,
            'metasResultado' => $metasResultado,
            'reportes' => $reportes,
            'actividades' => $actividades,
        ]);
    }

    public function reportar(Request $request, Dependencia $dependencia): View
    {
        $user = $request->user();
        abort_unless($user?->veTodosLosSectores() || $user?->perteneceADependencia($dependencia->id), 403);

        $seguimientos = Seguimiento::query()
            ->orderByDesc('vigencia')
            ->orderByDesc('mes')
            ->get();

        $seguimiento = $request->integer('seguimiento')
            ? $seguimientos->firstWhere('id', $request->integer('seguimiento'))
            : $seguimientos->first();

        if (! $seguimiento) {
            return view('intelligence.seguimiento-dependencias.reportar', [
                'dependencia' => $dependencia,
                'seguimientos' => $seguimientos,
                'seguimiento' => null,
                'filas' => collect(),
                'resumen' => $this->resumenReportesVacio(),
            ]);
        }

        $proyectoIds = collect()
            ->merge($dependencia->proyectos()->pluck('proyectos.id'))
            ->merge(Techo::query()
                ->withoutGlobalScopes()
                ->where('seguimiento_id', $seguimiento->id)
                ->where('dependencia_id', $dependencia->id)
                ->pluck('proyecto_id'))
            ->merge(ReporteProyecto::query()
                ->withoutGlobalScopes()
                ->where('seguimiento_id', $seguimiento->id)
                ->where('dependencia_id', $dependencia->id)
                ->pluck('proyecto_id'))
            ->filter()
            ->unique()
            ->values();

        $proyectos = Proyecto::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $proyectoIds->isNotEmpty() ? $proyectoIds : [0])
            ->with([
                'metasProducto',
                'actividades' => fn ($query) => $query
                    ->where('dependencia_id', $dependencia->id)
                    ->with('metaProducto')
                    ->orderBy('codigo')
                    ->orderBy('id'),
                'techos' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('seguimiento_id', $seguimiento->id)
                    ->where('dependencia_id', $dependencia->id)
                    ->with('fuente'),
                'reportes' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('seguimiento_id', $seguimiento->id)
                    ->where('dependencia_id', $dependencia->id)
                    ->with(['avances.actividad', 'ejecuciones.fuente']),
            ])
            ->orderByRaw("regexp_replace(bpin, '\\D', '', 'g')::numeric desc")
            ->orderByDesc('id')
            ->get();

        $filas = $proyectos->map(function (Proyecto $proyecto) use ($dependencia, $seguimiento): array {
            $reporte = $proyecto->reportes->first();
            $techos = $proyecto->techos;
            $actividades = $proyecto->actividades;
            $tieneTecho = $techos->isNotEmpty();
            $programado = (float) $actividades->sum(fn ($actividad): float => (float) ($actividad->cantidad_programada ?? 0));
            $avance = (float) ($reporte?->avances?->sum(fn ($avance): float => (float) $avance->cantidad) ?? 0);
            $comprometido = (float) ($reporte?->ejecuciones?->sum(fn ($ejecucion): float => (float) $ejecucion->comprometido) ?? 0);
            $obligado = (float) ($reporte?->ejecuciones?->sum(fn ($ejecucion): float => (float) $ejecucion->obligado) ?? 0);
            $pagado = (float) ($reporte?->ejecuciones?->sum(fn ($ejecucion): float => (float) $ejecucion->pagado) ?? 0);
            $historicas = $actividades->filter(fn ($actividad): bool => $actividad->esHistoricaConsolidada())->count();
            $operativas = $actividades->count() - $historicas;
            $estado = $reporte?->estado?->label() ?? ($tieneTecho ? 'Sin iniciar' : 'Sin techo en el corte');
            $estadoClase = $reporte?->estado?->cssClass() ?? ($tieneTecho ? 'status-pending' : 'status-error');

            return [
                'proyecto' => $proyecto,
                'reporte' => $reporte,
                'tiene_techo' => $tieneTecho,
                'estado' => $estado,
                'estado_clase' => $estadoClase,
                'techo' => (float) $techos->sum(fn (Techo $techo): float => (float) $techo->valor),
                'fuentes' => $techos
                    ->map(fn (Techo $techo): ?string => $techo->fuente?->etiqueta())
                    ->filter()
                    ->unique()
                    ->values(),
                'metas_producto' => $proyecto->metasProducto,
                'actividades' => $actividades,
                'actividades_historicas' => $historicas,
                'actividades_operativas' => $operativas,
                'programado' => $programado,
                'avance' => $avance,
                'avance_pct' => $programado > 0 ? ($avance / $programado) * 100 : 0.0,
                'comprometido' => $comprometido,
                'obligado' => $obligado,
                'pagado' => $pagado,
                'ejecucion_pct' => $techos->sum('valor') > 0 ? ($comprometido / (float) $techos->sum('valor')) * 100 : 0.0,
                'url_reporte' => $tieneTecho
                    ? Workspace::getUrl([
                        'workspace' => 'seguimiento-proyecto-reportar',
                        'record' => $seguimiento->id.'-'.$proyecto->id,
                        'dependencia' => $dependencia->id,
                    ])
                    : null,
            ];
        })->values();

        return view('intelligence.seguimiento-dependencias.reportar', [
            'dependencia' => $dependencia,
            'seguimientos' => $seguimientos,
            'seguimiento' => $seguimiento,
            'filas' => $filas,
            'resumen' => [
                'proyectos' => $filas->count(),
                'con_techo' => $filas->where('tiene_techo', true)->count(),
                'con_reporte' => $filas->filter(fn (array $fila): bool => $fila['reporte'] !== null)->count(),
                'pendientes' => $filas->where('tiene_techo', true)->filter(fn (array $fila): bool => $fila['reporte'] === null)->count(),
                'techo' => (float) $filas->sum('techo'),
                'comprometido' => (float) $filas->sum('comprometido'),
                'avance' => (float) $filas->sum('avance'),
                'programado' => (float) $filas->sum('programado'),
            ],
        ]);
    }

    public function indexDetail(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->canAccessManagementGoals(), 403);

        $datos = $request->validate([
            'relacion' => ['required', Rule::in(self::DETAIL_RELATIONS)],
        ]);

        $seguimiento = $this->seguimientoMasReciente();
        $dependencias = $this->dependenciasVisibles($request)->get();
        $dependenciaIds = $dependencias->pluck('id')->all();
        $relation = $datos['relacion'];

        return response()->json($this->detailPayload(
            titulo: $this->detailTitle($relation),
            registro: [
                'codigo' => 'Seguimiento',
                'nombre' => $seguimiento ? $seguimiento->etiqueta() : 'Sin corte registrado',
            ],
            grupos: match ($relation) {
                'dependencias' => $this->dependenciasDetailGroups($dependencias),
                'metas_producto' => $this->metasProductoDetailGroups($dependenciaIds),
                'proyectos', 'proyectos_reportados' => $this->proyectosDetailGroups($dependenciaIds, $seguimiento, $relation === 'proyectos_reportados'),
                'techo' => $this->techosDetailGroups($dependenciaIds, $seguimiento),
                'comprometido', 'ejecucion_financiera', 'obligado_pagado' => $this->ejecucionDetailGroups($dependenciaIds, $seguimiento, $relation),
                'avance_fisico' => $this->avanceFisicoDetailGroups($dependenciaIds, $seguimiento),
                'programacion_fisica' => $this->programacionFisicaDetailGroups($dependenciaIds, $seguimiento),
                default => [],
            },
        ));
    }

    public function detail(Request $request, Dependencia $dependencia): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->veTodosLosSectores() || $user?->perteneceADependencia($dependencia->id), 403);

        $datos = $request->validate([
            'relacion' => ['required', Rule::in(self::DETAIL_RELATIONS)],
        ]);

        $seguimiento = $this->seguimientoMasReciente();
        $relation = $datos['relacion'];

        return response()->json($this->detailPayload(
            titulo: $this->detailTitle($relation),
            registro: [
                'codigo' => $dependencia->codigo,
                'nombre' => $dependencia->nombre,
            ],
            grupos: match ($relation) {
                'dependencias' => $this->dependenciasDetailGroups(collect([$dependencia])),
                'metas_producto' => $this->metasProductoDetailGroups([$dependencia->id]),
                'proyectos', 'proyectos_reportados' => $this->proyectosDetailGroups([$dependencia->id], $seguimiento, $relation === 'proyectos_reportados'),
                'techo' => $this->techosDetailGroups([$dependencia->id], $seguimiento),
                'comprometido', 'ejecucion_financiera', 'obligado_pagado' => $this->ejecucionDetailGroups([$dependencia->id], $seguimiento, $relation),
                'avance_fisico' => $this->avanceFisicoDetailGroups([$dependencia->id], $seguimiento),
                'programacion_fisica' => $this->programacionFisicaDetailGroups([$dependencia->id], $seguimiento),
                default => [],
            },
        ));
    }

    private function seguimientoMasReciente(): ?Seguimiento
    {
        return Seguimiento::query()
            ->orderByDesc('vigencia')
            ->orderByDesc('mes')
            ->first();
    }

    private function detailTitle(string $relation): string
    {
        return match ($relation) {
            'dependencias' => 'Dependencias visibles',
            'metas_producto' => 'Metas producto relacionadas',
            'proyectos' => 'Proyectos del corte',
            'proyectos_reportados' => 'Proyectos reportados',
            'techo' => 'Detalle del techo',
            'comprometido' => 'Detalle del comprometido',
            'ejecucion_financiera' => 'Detalle de ejecución financiera',
            'obligado_pagado' => 'Detalle de obligado y pagado',
            'avance_fisico' => 'Detalle del avance físico',
            'programacion_fisica' => 'Detalle de programación física',
            default => 'Detalle',
        };
    }

    /**
     * @param  list<array{titulo: string, items: list<array<string, mixed>>, nota?: string, vacio?: string}>  $grupos
     * @return array<string, mixed>
     */
    private function detailPayload(string $titulo, array $registro, array $grupos): array
    {
        $total = collect($grupos)->sum(fn (array $grupo): int => count($grupo['items'] ?? []));

        return [
            'titulo' => $titulo,
            'registro' => $registro,
            'total' => $total,
            'resumen' => [['label' => $total === 1 ? 'registro' : 'registros', 'valor' => $total]],
            'grupos' => $grupos,
        ];
    }

    /**
     * @param  Collection<int, Dependencia>  $dependencias
     * @return list<array<string, mixed>>
     */
    private function dependenciasDetailGroups(Collection $dependencias): array
    {
        return [[
            'titulo' => 'Dependencias',
            'items' => $dependencias
                ->sortBy('nombre')
                ->map(fn (Dependencia $dependencia): array => [
                    'codigo' => $dependencia->sigla ?: $dependencia->codigo,
                    'nombre' => $dependencia->nombre,
                    'url' => Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $dependencia->id]),
                ])
                ->values()
                ->all(),
            'vacio' => 'No hay dependencias visibles con los filtros aplicados.',
        ]];
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function metasProductoDetailGroups(array $dependenciaIds): array
    {
        if ($dependenciaIds === []) {
            return [];
        }

        $dependencias = Dependencia::query()->whereIn('id', $dependenciaIds)->get()->keyBy('id');
        $metaIdsByDependencia = collect($dependenciaIds)
            ->mapWithKeys(fn (int $dependenciaId): array => [$dependenciaId => $this->metaProductoIdsPorDependencia($dependenciaId)]);
        $metas = MetaProducto::query()
            ->whereIn('id', $metaIdsByDependencia->flatMap(fn (Collection $ids): Collection => $ids)->unique()->values())
            ->with(['metaResultado'])
            ->get()
            ->keyBy('id');

        return $metaIdsByDependencia
            ->map(function (Collection $ids, int $dependenciaId) use ($dependencias, $metas): array {
                return [
                    'titulo' => $dependencias->get($dependenciaId)?->nombre ?? 'Dependencia '.$dependenciaId,
                    'items' => $ids
                        ->map(fn (int $id) => $metas->get($id))
                        ->filter()
                        ->sortBy('codigo')
                        ->map(fn (MetaProducto $meta): array => [
                            'codigo' => $meta->codigo,
                            'nombre' => $meta->nombre,
                            'url' => Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $meta->id]),
                            'extra' => [
                                ['label' => 'Meta resultado', 'valor' => $meta->metaResultado?->codigo ?? 'Sin meta resultado'],
                            ],
                        ])
                        ->values()
                        ->all(),
                    'vacio' => 'No hay metas producto relacionadas.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function proyectosDetailGroups(array $dependenciaIds, ?Seguimiento $seguimiento, bool $soloReportados): array
    {
        if ($dependenciaIds === []) {
            return [];
        }

        $dependencias = Dependencia::query()->whereIn('id', $dependenciaIds)->get()->keyBy('id');
        $rows = $soloReportados && $seguimiento
            ? DB::table('reportes_proyecto')
                ->where('seguimiento_id', $seguimiento->id)
                ->whereIn('dependencia_id', $dependenciaIds)
                ->get(['dependencia_id', 'proyecto_id', DB::raw("'Reporte creado' as origen")])
            : ($seguimiento
                ? DB::table('techos')
                    ->where('seguimiento_id', $seguimiento->id)
                    ->whereIn('dependencia_id', $dependenciaIds)
                    ->get(['dependencia_id', 'proyecto_id', DB::raw("'Con techo' as origen")])
                    ->merge(DB::table('reportes_proyecto')->where('seguimiento_id', $seguimiento->id)->whereIn('dependencia_id', $dependenciaIds)->get(['dependencia_id', 'proyecto_id', DB::raw("'Reporte creado' as origen")]))
                : DB::table('dependencia_proyecto')
                    ->whereIn('dependencia_id', $dependenciaIds)
                    ->get(['dependencia_id', 'proyecto_id', DB::raw("'Vínculo histórico dependencia-proyecto' as origen")]));

        $proyectos = Proyecto::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('proyecto_id')->unique()->values())
            ->get()
            ->keyBy('id');

        return $rows
            ->groupBy('dependencia_id')
            ->map(function (Collection $filas, int $dependenciaId) use ($dependencias, $proyectos): array {
                return [
                    'titulo' => $dependencias->get($dependenciaId)?->nombre ?? 'Dependencia '.$dependenciaId,
                    'items' => $filas
                        ->pluck('proyecto_id')
                        ->unique()
                        ->map(fn (int $id) => $proyectos->get($id))
                        ->filter()
                        ->sortByDesc(fn (Proyecto $proyecto): string => preg_replace('/\D+/', '', $proyecto->bpin))
                        ->map(fn (Proyecto $proyecto): array => [
                            'codigo' => $proyecto->bpin,
                            'nombre' => $proyecto->nombre,
                            'url' => Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->id]),
                            'extra' => [
                                [
                                    'label' => 'Origen en este detalle',
                                    'valor' => $filas
                                        ->where('proyecto_id', $proyecto->id)
                                        ->pluck('origen')
                                        ->filter()
                                        ->unique()
                                        ->implode(', '),
                                ],
                            ],
                        ])
                        ->values()
                        ->all(),
                    'vacio' => 'No hay proyectos en el corte seleccionado.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function techosDetailGroups(array $dependenciaIds, ?Seguimiento $seguimiento): array
    {
        if (! $seguimiento || $dependenciaIds === []) {
            return [];
        }

        return Techo::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->whereIn('dependencia_id', $dependenciaIds)
            ->with(['dependencia', 'proyecto', 'fuente'])
            ->get()
            ->groupBy('dependencia_id')
            ->map(fn (Collection $techos): array => [
                'titulo' => $techos->first()?->dependencia?->nombre ?? 'Dependencia',
                'nota' => $this->money((float) $techos->sum(fn (Techo $techo): float => (float) $techo->valor)),
                'tabla_resumen' => [
                    'columns' => ['Concepto', 'Valor'],
                    'rows' => [
                        ['Filas de techo incluidas', number_format($techos->count(), 0, ',', '.')],
                        ['BPIN únicos', number_format($techos->pluck('proyecto_id')->unique()->count(), 0, ',', '.')],
                        ['Fuentes únicas', number_format($techos->pluck('fuente_financiacion_id')->unique()->count(), 0, ',', '.')],
                        ['Suma del techo', $this->money((float) $techos->sum(fn (Techo $techo): float => (float) $techo->valor))],
                    ],
                ],
                'items' => $techos
                    ->sortBy([
                        fn (Techo $techo): string => (string) ($techo->proyecto?->bpin ?? ''),
                        fn (Techo $techo): string => (string) ($techo->fuente?->codigo ?? ''),
                    ])
                    ->map(fn (Techo $techo): array => [
                        'codigo' => $techo->proyecto?->bpin,
                        'nombre' => $techo->proyecto?->nombre ?? 'Proyecto sin nombre',
                        'url' => $techo->proyecto ? Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $techo->proyecto->id]) : null,
                        'extra' => [
                            ['label' => 'Fuente', 'valor' => $techo->fuente?->etiqueta() ?? 'Sin fuente'],
                            ['label' => 'Valor', 'valor' => $this->money((float) $techo->valor)],
                        ],
                    ])
                    ->values()
                    ->all(),
                'vacio' => 'No hay techos para este corte.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function ejecucionDetailGroups(array $dependenciaIds, ?Seguimiento $seguimiento, string $relation): array
    {
        if (! $seguimiento || $dependenciaIds === []) {
            return [];
        }

        $reportes = ReporteProyecto::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->whereIn('dependencia_id', $dependenciaIds)
            ->with(['dependencia', 'proyecto', 'ejecuciones.actividad', 'ejecuciones.fuente'])
            ->get();

        $metricLabel = match ($relation) {
            'comprometido' => 'Comprometido',
            'obligado_pagado' => 'Obligado / pagado',
            default => 'Ejecución financiera',
        };

        $metricValue = fn ($ejecucion): float => match ($relation) {
            'comprometido' => (float) $ejecucion->comprometido,
            'obligado_pagado' => (float) $ejecucion->obligado + (float) $ejecucion->pagado,
            default => (float) $ejecucion->comprometido + (float) $ejecucion->obligado + (float) $ejecucion->pagado,
        };

        return $reportes
            ->groupBy('dependencia_id')
            ->map(fn (Collection $filas): array => [
                'titulo' => $filas->first()?->dependencia?->nombre ?? 'Dependencia',
                'nota' => $relation === 'obligado_pagado'
                    ? 'Obligado '.$this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->obligado)).' · Pagado '.$this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->pagado))
                    : $this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => match ($relation) {
                        'comprometido' => (float) $ejecucion->comprometido,
                        default => (float) $ejecucion->comprometido + (float) $ejecucion->obligado + (float) $ejecucion->pagado,
                    })),
                'tabla_resumen' => [
                    'columns' => ['Concepto', 'Valor'],
                    'rows' => [
                        ['Reportes de proyecto incluidos', number_format($filas->count(), 0, ',', '.')],
                        ['BPIN únicos', number_format($filas->pluck('proyecto_id')->unique()->count(), 0, ',', '.')],
                        ['Registros de ejecución', number_format($filas->flatMap->ejecuciones->count(), 0, ',', '.')],
                        ['Comprometido', $this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->comprometido))],
                        ['Obligado', $this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->obligado))],
                        ['Pagado', $this->money((float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->pagado))],
                    ],
                ],
                'items' => $filas
                    ->flatMap(fn (ReporteProyecto $reporte): Collection => $reporte->ejecuciones
                        ->filter(fn ($ejecucion): bool => $metricValue($ejecucion) > 0)
                        ->map(fn ($ejecucion): array => [
                        'codigo' => $reporte->proyecto?->bpin,
                        'nombre' => $reporte->proyecto?->nombre ?? 'Proyecto sin nombre',
                        'url' => $reporte->proyecto ? Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $reporte->proyecto->id]) : null,
                        'extra' => [
                            ['label' => 'Actividad', 'valor' => ($ejecucion->actividad?->codigo ?: 'ACT-'.$ejecucion->actividad_id).' — '.($ejecucion->actividad?->nombre ?? 'Sin actividad')],
                            ['label' => 'Fuente', 'valor' => $ejecucion->fuente?->etiqueta() ?? 'Sin fuente'],
                            ['label' => 'Dato consultado', 'valor' => $metricLabel],
                            ['label' => 'Comprometido', 'valor' => $this->money((float) $ejecucion->comprometido)],
                            ['label' => 'Obligado', 'valor' => $this->money((float) $ejecucion->obligado)],
                            ['label' => 'Pagado', 'valor' => $this->money((float) $ejecucion->pagado)],
                        ],
                    ]))
                    ->values()
                    ->all(),
                'vacio' => 'No hay ejecución financiera reportada.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function programacionFisicaDetailGroups(array $dependenciaIds, ?Seguimiento $seguimiento): array
    {
        if (! $seguimiento || $dependenciaIds === []) {
            return [];
        }

        $dependencias = Dependencia::query()->whereIn('id', $dependenciaIds)->get()->keyBy('id');
        $actividades = DB::table('actividades')
            ->join('proyectos', 'proyectos.id', '=', 'actividades.proyecto_id')
            ->leftJoin('metas_producto', 'metas_producto.id', '=', 'actividades.meta_producto_id')
            ->whereIn('actividades.dependencia_id', $dependenciaIds)
            ->whereNull('actividades.deleted_at')
            ->where('actividades.cantidad_programada', '>', 0)
            ->select([
                'actividades.id',
                'actividades.dependencia_id',
                'actividades.codigo',
                'actividades.nombre',
                'actividades.cantidad_programada',
                'actividades.origen',
                'proyectos.id as proyecto_id',
                'proyectos.bpin',
                'proyectos.nombre as proyecto_nombre',
                'metas_producto.codigo as meta_codigo',
            ])
            ->get();

        return $actividades
            ->groupBy('dependencia_id')
            ->map(fn (Collection $filas, int $dependenciaId): array => [
                'titulo' => $dependencias->get($dependenciaId)?->nombre ?? 'Dependencia '.$dependenciaId,
                'nota' => number_format((float) $filas->sum(fn ($actividad): float => (float) $actividad->cantidad_programada), 2, ',', '.'),
                'items' => $filas
                    ->map(fn ($actividad): array => [
                        'codigo' => $actividad->bpin,
                        'nombre' => $actividad->proyecto_nombre,
                        'url' => Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $actividad->proyecto_id]),
                        'extra' => [
                            ['label' => 'Actividad', 'valor' => ($actividad->codigo ?: 'ACT-'.$actividad->id).' — '.$actividad->nombre],
                            ['label' => 'Meta producto', 'valor' => $actividad->meta_codigo ?? 'Sin meta producto'],
                            ['label' => 'Programación física', 'valor' => number_format((float) $actividad->cantidad_programada, 2, ',', '.')],
                            ['label' => 'Tipo de actividad', 'valor' => Actividad::origenes()[$actividad->origen] ?? 'Captura sectorial'],
                        ],
                    ])
                    ->values()
                    ->all(),
                'vacio' => 'No hay programación física para este corte.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return list<array<string, mixed>>
     */
    private function avanceFisicoDetailGroups(array $dependenciaIds, ?Seguimiento $seguimiento): array
    {
        if (! $seguimiento || $dependenciaIds === []) {
            return [];
        }

        $reportes = ReporteProyecto::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->whereIn('dependencia_id', $dependenciaIds)
            ->with(['dependencia', 'proyecto', 'avances.actividad.metaProducto'])
            ->get();

        return $reportes
            ->groupBy('dependencia_id')
            ->map(function (Collection $filas): array {
                $actividadIds = $filas->flatMap->avances->pluck('actividad_id')->filter()->unique();
                $programado = (float) Actividad::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $actividadIds->isNotEmpty() ? $actividadIds : [0])
                    ->sum('cantidad_programada');
                $avance = (float) $filas->flatMap->avances->sum(fn ($avance): float => (float) $avance->cantidad);

                return [
                    'titulo' => $filas->first()?->dependencia?->nombre ?? 'Dependencia',
                    'nota' => number_format($avance, 2, ',', '.'),
                    'tabla_resumen' => [
                        'columns' => ['Concepto', 'Valor'],
                        'rows' => [
                            ['Reportes de proyecto incluidos', number_format($filas->count(), 0, ',', '.')],
                            ['BPIN únicos con avance', number_format($filas->pluck('proyecto_id')->unique()->count(), 0, ',', '.')],
                            ['Actividades con avance', number_format($actividadIds->count(), 0, ',', '.')],
                            ['Programación física de esas actividades', number_format($programado, 2, ',', '.')],
                            ['Avance físico reportado', number_format($avance, 2, ',', '.')],
                            ['Porcentaje avance / programación', $programado > 0 ? number_format(($avance / $programado) * 100, 2, ',', '.').' %' : '0,00 %'],
                        ],
                    ],
                    'items' => $filas
                    ->flatMap(fn (ReporteProyecto $reporte): Collection => $reporte->avances->map(fn ($avance): array => [
                        'codigo' => $reporte->proyecto?->bpin,
                        'nombre' => $reporte->proyecto?->nombre ?? 'Proyecto sin nombre',
                        'url' => $reporte->proyecto ? Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $reporte->proyecto->id]) : null,
                        'extra' => [
                            ['label' => 'Actividad', 'valor' => ($avance->actividad?->codigo ?: 'ACT-'.$avance->actividad_id).' — '.($avance->actividad?->nombre ?? 'Sin actividad')],
                            ['label' => 'Meta producto', 'valor' => $avance->actividad?->metaProducto?->codigo ?? 'Sin meta producto'],
                            ['label' => 'Avance físico', 'valor' => number_format((float) $avance->cantidad, 2, ',', '.')],
                            ['label' => 'Fecha', 'valor' => $avance->fecha_ejecucion?->format('d/m/Y') ?? 'Sin fecha'],
                        ],
                    ]))
                    ->values()
                    ->all(),
                    'vacio' => 'No hay avances físicos reportados.',
                ];
            })
            ->values()
            ->all();
    }

    private function money(float|int $value): string
    {
        return '$'.number_format((float) $value, 0, ',', '.');
    }

    private function dependenciasVisibles(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        return Dependencia::query()
            ->where('activo', true)
            ->when(! ($user?->veTodosLosSectores() ?? false), fn ($query) => $query->whereIn('id', $user?->dependenciaIdsAsignadas() ?: [0]))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(fn ($inner) => $inner
                    ->where('codigo', 'like', $like)
                    ->orWhere('sigla', 'like', $like)
                    ->orWhere('nombre', 'like', $like));
            })
            ->orderBy('nombre');
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return Collection<int, int>
     */
    private function conteoMetasProductoPorDependencia(array $dependenciaIds): Collection
    {
        if ($dependenciaIds === []) {
            return collect();
        }

        $directas = DB::table('metas_producto')
            ->whereIn('dependencia_id', $dependenciaIds)
            ->whereNull('deleted_at')
            ->get(['dependencia_id', DB::raw('id as meta_producto_id'), DB::raw("'Catálogo directo de la meta' as origen")]);

        $porProyecto = DB::table('dependencia_proyecto')
            ->join('meta_producto_proyecto', 'meta_producto_proyecto.proyecto_id', '=', 'dependencia_proyecto.proyecto_id')
            ->join('metas_producto', 'metas_producto.id', '=', 'meta_producto_proyecto.meta_producto_id')
            ->whereIn('dependencia_proyecto.dependencia_id', $dependenciaIds)
            ->whereNull('metas_producto.deleted_at')
            ->get([
                'dependencia_proyecto.dependencia_id',
                'meta_producto_proyecto.meta_producto_id',
                DB::raw("'Vínculo proyecto-BPIN' as origen"),
            ]);

        $porActividad = DB::table('actividades')
            ->join('metas_producto', 'metas_producto.id', '=', 'actividades.meta_producto_id')
            ->whereIn('actividades.dependencia_id', $dependenciaIds)
            ->whereNotNull('actividades.meta_producto_id')
            ->whereNull('actividades.deleted_at')
            ->whereNull('metas_producto.deleted_at')
            ->get([
                'actividades.dependencia_id',
                'actividades.meta_producto_id',
                DB::raw("'Actividad de reporte' as origen"),
            ]);

        return $directas
            ->merge($porProyecto)
            ->merge($porActividad)
            ->groupBy('dependencia_id')
            ->map(fn (Collection $filas): int => $filas->pluck('meta_producto_id')->filter()->unique()->count());
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return Collection<int, int>
     */
    private function conteoProyectosPorDependencia(array $dependenciaIds, ?Seguimiento $seguimiento): Collection
    {
        if ($dependenciaIds === []) {
            return collect();
        }

        $porTecho = $seguimiento
            ? DB::table('techos')
                ->where('seguimiento_id', $seguimiento->id)
                ->whereIn('dependencia_id', $dependenciaIds)
                ->get(['dependencia_id', 'proyecto_id'])
            : collect();

        $porReporte = $seguimiento
            ? DB::table('reportes_proyecto')
                ->where('seguimiento_id', $seguimiento->id)
                ->whereIn('dependencia_id', $dependenciaIds)
                ->get(['dependencia_id', 'proyecto_id'])
            : collect();

        $porVinculoHistorico = $seguimiento
            ? collect()
            : DB::table('dependencia_proyecto')
                ->whereIn('dependencia_id', $dependenciaIds)
                ->get(['dependencia_id', 'proyecto_id']);

        return $porTecho
            ->merge($porReporte)
            ->merge($porVinculoHistorico)
            ->groupBy('dependencia_id')
            ->map(fn (Collection $filas): int => $filas->pluck('proyecto_id')->filter()->unique()->count());
    }

    /**
     * @return Collection<int, int>
     */
    private function metaProductoIdsPorDependencia(int $dependenciaId): Collection
    {
        return $this->conteoMetasProductoDetallePorDependencia($dependenciaId)
            ->pluck('meta_producto_id')
            ->filter()
            ->unique()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function conteoMetasProductoDetallePorDependencia(int $dependenciaId): Collection
    {
        $directas = DB::table('metas_producto')
            ->where('dependencia_id', $dependenciaId)
            ->whereNull('deleted_at')
            ->get([DB::raw($dependenciaId.' as dependencia_id'), DB::raw('id as meta_producto_id'), DB::raw("'Catálogo directo de la meta' as origen")]);

        $porProyecto = DB::table('dependencia_proyecto')
            ->join('meta_producto_proyecto', 'meta_producto_proyecto.proyecto_id', '=', 'dependencia_proyecto.proyecto_id')
            ->join('metas_producto', 'metas_producto.id', '=', 'meta_producto_proyecto.meta_producto_id')
            ->where('dependencia_proyecto.dependencia_id', $dependenciaId)
            ->whereNull('metas_producto.deleted_at')
            ->get([
                'dependencia_proyecto.dependencia_id',
                'meta_producto_proyecto.meta_producto_id',
                DB::raw("'Vínculo proyecto-BPIN' as origen"),
            ]);

        $porActividad = DB::table('actividades')
            ->join('metas_producto', 'metas_producto.id', '=', 'actividades.meta_producto_id')
            ->where('actividades.dependencia_id', $dependenciaId)
            ->whereNotNull('actividades.meta_producto_id')
            ->whereNull('actividades.deleted_at')
            ->whereNull('metas_producto.deleted_at')
            ->get([
                'actividades.dependencia_id',
                'actividades.meta_producto_id',
                DB::raw("'Actividad de reporte' as origen"),
            ]);

        return $directas->merge($porProyecto)->merge($porActividad);
    }

    /**
     * @param  list<int>  $dependenciaIds
     * @return Collection<int, array<string, mixed>>
     */
    private function metricasPorDependencia(Seguimiento $seguimiento, array $dependenciaIds): Collection
    {
        $base = collect($dependenciaIds)->mapWithKeys(fn (int $id): array => [$id => $this->metricasVacias()]);

        $reportes = ReporteProyecto::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->whereIn('dependencia_id', $dependenciaIds ?: [0])
            ->with([
                'avances.actividad',
                'ejecuciones',
            ])
            ->get()
            ->groupBy('dependencia_id');

        $techos = Techo::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->whereIn('dependencia_id', $dependenciaIds ?: [0])
            ->selectRaw('dependencia_id, SUM(valor) AS techo')
            ->groupBy('dependencia_id')
            ->pluck('techo', 'dependencia_id');

        return $base->map(function (array $metricas, int $dependenciaId) use ($reportes, $techos): array {
            $filas = $reportes->get($dependenciaId, collect());
            $actividades = $filas
                ->flatMap(fn (ReporteProyecto $reporte) => $reporte->avances->pluck('actividad')->merge($reporte->ejecuciones->pluck('actividad')))
                ->filter()
                ->unique('id');

            $metricas['reportes'] = $filas->count();
            $metricas['proyectos_reportados'] = $filas->pluck('proyecto_id')->unique()->count();
            $metricas['techo'] = (float) ($techos[$dependenciaId] ?? 0);
            $metricas['programacion_fisica'] = (float) $actividades->sum(fn ($actividad): float => (float) ($actividad->cantidad_programada ?? 0));
            $metricas['avance_fisico'] = (float) $filas->flatMap->avances->sum(fn ($avance): float => (float) $avance->cantidad);
            $metricas['comprometido'] = (float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->comprometido);
            $metricas['obligado'] = (float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->obligado);
            $metricas['pagado'] = (float) $filas->flatMap->ejecuciones->sum(fn ($ejecucion): float => (float) $ejecucion->pagado);
            $metricas['avance_fisico_pct'] = $metricas['programacion_fisica'] > 0
                ? ($metricas['avance_fisico'] / $metricas['programacion_fisica']) * 100
                : 0.0;
            $metricas['ejecucion_pct'] = $metricas['techo'] > 0
                ? ($metricas['comprometido'] / $metricas['techo']) * 100
                : 0.0;

            return $metricas;
        });
    }

    /**
     * @param  Collection<int, ReporteProyecto>  $reportes
     * @return Collection<int, array<string, mixed>>
     */
    private function actividadesDeReportes(Collection $reportes): Collection
    {
        return $reportes
            ->flatMap(function (ReporteProyecto $reporte): Collection {
                $actividades = $reporte->avances
                    ->pluck('actividad')
                    ->merge($reporte->ejecuciones->pluck('actividad'))
                    ->filter()
                    ->unique('id')
                    ->values();

                return $actividades->map(fn ($actividad): array => [
                    'actividad' => $actividad,
                    'proyecto' => $reporte->proyecto,
                    'reporte' => $reporte,
                    'meta_producto' => $actividad->metaProducto,
                    'programado' => (float) ($actividad->cantidad_programada ?? 0),
                    'avance' => (float) $reporte->avances
                        ->where('actividad_id', $actividad->id)
                        ->sum(fn ($avance): float => (float) $avance->cantidad),
                    'comprometido' => (float) $reporte->ejecuciones
                        ->where('actividad_id', $actividad->id)
                        ->sum(fn ($ejecucion): float => (float) $ejecucion->comprometido),
                    'obligado' => (float) $reporte->ejecuciones
                        ->where('actividad_id', $actividad->id)
                        ->sum(fn ($ejecucion): float => (float) $ejecucion->obligado),
                    'pagado' => (float) $reporte->ejecuciones
                        ->where('actividad_id', $actividad->id)
                        ->sum(fn ($ejecucion): float => (float) $ejecucion->pagado),
                ]);
            })
            ->sortBy([
                fn (array $fila): string => (string) ($fila['proyecto']?->bpin ?? ''),
                fn (array $fila): string => (string) ($fila['meta_producto']?->codigo ?? ''),
                fn (array $fila): string => (string) ($fila['actividad']?->codigo ?? ''),
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function metricasVacias(): array
    {
        return [
            'reportes' => 0,
            'proyectos_reportados' => 0,
            'techo' => 0.0,
            'programacion_fisica' => 0.0,
            'avance_fisico' => 0.0,
            'avance_fisico_pct' => 0.0,
            'comprometido' => 0.0,
            'obligado' => 0.0,
            'pagado' => 0.0,
            'ejecucion_pct' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenReportesVacio(): array
    {
        return [
            'proyectos' => 0,
            'con_techo' => 0,
            'con_reporte' => 0,
            'pendientes' => 0,
            'techo' => 0.0,
            'comprometido' => 0.0,
            'avance' => 0.0,
            'programado' => 0.0,
        ];
    }
}
