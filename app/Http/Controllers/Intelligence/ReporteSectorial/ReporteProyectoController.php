<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\FuenteFinanciacion;
use App\Models\PasivaLinea;
use App\Models\Proyecto;
use App\Models\ProyectoTrazador;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reporte por actividad de un BPIN. {proyecto} y {actividad} se resuelven con el scope sectorial:
 * un BPIN de otro sector responde 404 aunque se fuerce el ID en la URL.
 */
class ReporteProyectoController extends Controller
{
    public function __construct(private readonly ServicioReporteSectorial $servicio) {}

    public function show(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): View
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('view', $reporte);

        $techos = Techo::query()
            ->delSeguimiento($seguimiento)
            ->where('proyecto_id', $proyecto->id)
            ->where('dependencia_id', $reporte->dependencia_id)
            ->with(['fuente', 'historial.usuario'])
            ->withSum('lineas as techo_asignado_pasiva', 'apropiacion_definitiva')
            ->withSum('lineas as techo_comprometido_pasiva', 'compromisos')
            ->withSum('lineas as techo_obligado_pasiva', 'obligaciones')
            ->withSum('lineas as techo_pagado_pasiva', 'pagos')
            ->get();

        $actividades = Actividad::query()
            ->where('proyecto_id', $proyecto->id)
            ->where('dependencia_id', $reporte->dependencia_id)
            ->with([
                'metaProducto',
                'programaciones' => fn ($query) => $query->where('vigencia', $seguimiento->vigencia)->with('fuente'),
            ])
            ->orderBy('id')
            ->get();

        $ejecuciones = $reporte->exists ? $reporte->ejecuciones()->get()->keyBy(fn ($e): string => $e->actividad_id.'-'.$e->fuente_financiacion_id) : collect();
        $avances = $reporte->exists ? $reporte->avances()->with('evidencias.subidoPor')->get()->keyBy('actividad_id') : collect();
        $reportadoPorFuente = $ejecuciones->groupBy('fuente_financiacion_id')->map(fn ($filas): array => [
            'comprometido' => (float) $filas->sum('comprometido'),
            'obligado' => (float) $filas->sum('obligado'),
            'pagado' => (float) $filas->sum('pagado'),
        ]);
        $focalizacionActual = $reporte->exists ? $reporte->focalizaciones()->get()->keyBy('municipio_id') : collect();
        $distribuciones = $reporte->exists
            ? $reporte->distribuciones()->with(['fuente', 'municipio', 'actividad'])->get()
            : collect();
        $regionalizaPorMeta = $proyecto->regionalizacion_modo === Proyecto::REGIONALIZACION_META
            || ($proyecto->regionalizacion_modo === null && $seguimiento->regionalizaPorMeta());
        $metasRegionalizadas = $proyecto->regionalizacion_modo === Proyecto::REGIONALIZACION_META
            ? $proyecto->metasProducto()->wherePivot('regionaliza', true)->pluck('metas_producto.id')
            : null;
        $actividadesRegionalizadas = $metasRegionalizadas === null
            ? $actividades
            : $actividades->filter(fn (Actividad $actividad): bool => $metasRegionalizadas->contains($actividad->meta_producto_id))->values();
        $requiereRegionalizacion = ($seguimiento->usaFocalizacion() || $seguimiento->usaDistribucionFinanciera()) && $proyecto->requiereFocalizacionMensual();
        $focalizacionPrevia = $requiereRegionalizacion ? $this->servicio->focalizacionPrevia($reporte) : [];
        $proyectoTrazadores = ProyectoTrazador::query()->where('proyecto_id', $proyecto->id)->where('activo', true)
            ->with(['trazador', 'politicaPublica', 'categorias' => fn ($query) => $query->where('activo', true)->with('categoria')])
            ->orderBy('trazador_id')->get();
        $totalesTrazadores = $this->servicio->totalesFinancierosPorFuente($reporte);
        $fuentesTrazadores = FuenteFinanciacion::query()->whereIn('id', $totalesTrazadores->keys())->get()->keyBy('id');
        $distribucionesTrazadores = $reporte->exists
            ? $reporte->distribucionesTrazadores()->get()->keyBy(fn ($fila): string => $fila->proyecto_trazador_id.'-'.$fila->trazador_categoria_id.'-'.$fila->fuente_financiacion_id)
            : collect();
        if ($distribucionesTrazadores->isEmpty() && $proyectoTrazadores->isNotEmpty()) {
            $reportePrevio = ReporteProyecto::sinFiltroSectorial()->where('proyecto_id', $proyecto->id)->where('dependencia_id', $reporte->dependencia_id)
                ->whereHas('seguimiento', fn ($query) => $query->where('fecha_corte', '<', $seguimiento->fecha_corte))
                ->with('seguimiento')->get()->sortByDesc('seguimiento.fecha_corte')->first();
            $distribucionesTrazadores = $reportePrevio?->distribucionesTrazadores()->get()->keyBy(fn ($fila): string => $fila->proyecto_trazador_id.'-'.$fila->trazador_categoria_id.'-'.$fila->fuente_financiacion_id) ?? collect();
        }

        return view('intelligence.reporte-sectorial.proyectos.show', [
            'seguimiento' => $seguimiento,
            'proyecto' => $proyecto,
            'reporte' => $reporte,
            'dependencias' => $this->servicio->dependenciasConTecho($seguimiento, $proyecto),
            'techos' => $techos,
            'reportadoPorFuente' => $reportadoPorFuente,
            'actividades' => $actividades,
            'actividadesRegionalizadas' => $actividadesRegionalizadas,
            'regionalizaPorMeta' => $regionalizaPorMeta,
            'ejecuciones' => $ejecuciones,
            'avances' => $avances,
            'municipios' => $requiereRegionalizacion ? $proyecto->municipiosParaRegionalizacion() : collect(),
            'focalizacionActual' => $focalizacionActual,
            'focalizacionPrevia' => $focalizacionPrevia,
            'distribuciones' => $distribuciones,
            'proyectoTrazadores' => $proyectoTrazadores,
            'totalesTrazadores' => $totalesTrazadores,
            'fuentesTrazadores' => $fuentesTrazadores,
            'distribucionesTrazadores' => $distribucionesTrazadores,
            'pendientes' => $this->servicio->pendientes($reporte),
            'puedeEditar' => $request->user()->can('update', $reporte),
            'puedeRevisar' => $request->user()->can('revisar', $reporte),
        ]);
    }

    public function storeActividad(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);

        $datos = $request->validate([
            'codigo' => ['nullable', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:2000'],
            'unidad_medida' => ['nullable', 'string', 'max:120'],
            'cantidad_programada' => ['nullable', 'numeric', 'min:0'],
            'programacion' => ['array'],
            'programacion.*.fuente_financiacion_id' => ['required', 'integer', Rule::exists('techos', 'fuente_financiacion_id')->where('seguimiento_id', $seguimiento->id)->where('proyecto_id', $proyecto->id)->where('dependencia_id', $reporte->dependencia_id)],
            'programacion.*.valor_asignado' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->servicio->crearActividad(
            $seguimiento,
            $proyecto,
            $reporte->dependencia,
            collect($datos)->only(['codigo', 'nombre', 'unidad_medida', 'cantidad_programada'])->all(),
            collect($datos['programacion'] ?? [])->map(fn (array $fila): array => ['fuente_financiacion_id' => (int) $fila['fuente_financiacion_id'], 'valor_asignado' => (float) ($fila['valor_asignado'] ?? 0)])->values()->all(),
            $request->user(),
        );

        return $this->volver($seguimiento, $proyecto, $reporte, 'Actividad creada.');
    }

    public function updateEjecucion(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);

        $datos = $request->validate([
            'ejecucion' => ['required', 'array', 'min:1'],
            'ejecucion.*.actividad_id' => ['required', 'integer'],
            'ejecucion.*.fuente_financiacion_id' => ['required', 'integer', 'exists:fuentes_financiacion,id'],
            'ejecucion.*.programado' => ['nullable', 'numeric', 'min:0'],
            'ejecucion.*.comprometido' => ['required', 'numeric', 'min:0'],
            'ejecucion.*.obligado' => ['required', 'numeric', 'min:0'],
            'ejecucion.*.pagado' => ['required', 'numeric', 'min:0'],
        ]);

        $this->servicio->guardarEjecucion($reporte, array_values($datos['ejecucion']));

        return $this->volver($seguimiento, $proyecto, $reporte, 'Ejecución financiera guardada.');
    }

    public function storeAvance(Request $request, Seguimiento $seguimiento, Proyecto $proyecto, Actividad $actividad): RedirectResponse
    {
        abort_unless($actividad->proyecto_id === $proyecto->id, 404);
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);
        $avanceExistente = $reporte->exists
            ? $reporte->avances()->withCount('evidencias')->where('actividad_id', $actividad->id)->first()
            : null;

        $datos = $request->validate([
            'cantidad' => ['required', 'numeric', 'min:0'],
            'fecha_ejecucion' => ['nullable', 'required_unless:cantidad,0', 'date', 'before_or_equal:'.$seguimiento->fecha_corte->toDateString()],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'evidencias' => [
                'array',
                Rule::requiredIf(fn (): bool => $actividad->exigeEvidencia() && (float) $request->input('cantidad', 0) > 0 && blank($request->input('evidencia_url')) && (int) ($avanceExistente?->evidencias_count ?? 0) === 0),
            ],
            'evidencias.*' => ['file', 'extensions:'.implode(',', config('reporte_sectorial.evidencias.mimes')), 'max:'.config('reporte_sectorial.evidencias.max_kb')],
            'evidencia_url' => ['nullable', 'url', 'max:2000'],
            'descripcion_evidencia' => ['nullable', 'string', 'max:1000'],
        ], [
            'fecha_ejecucion.required_unless' => 'Indique la fecha de ejecución del avance.',
            'fecha_ejecucion.before_or_equal' => 'La fecha de ejecución no puede ser posterior a la fecha de corte.',
            'evidencias.required' => 'Adjunte al menos una evidencia para reportar avance físico.',
        ]);

        $this->servicio->guardarAvance(
            $reporte,
            $actividad,
            (float) $datos['cantidad'],
            $datos['fecha_ejecucion'] ?? null,
            $datos['descripcion'] ?? null,
            $request->file('evidencias', []),
            $datos['descripcion_evidencia'] ?? null,
            $datos['evidencia_url'] ?? null,
            $request->user(),
        );

        return $this->volver($seguimiento, $proyecto, $reporte, 'Avance físico guardado.');
    }

    public function storeObservacion(Request $request, Seguimiento $seguimiento, Proyecto $proyecto, Actividad $actividad): RedirectResponse
    {
        abort_unless($actividad->proyecto_id === $proyecto->id, 404);
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);
        $datos = $request->validate(['observacion' => ['required', 'string', 'max:4000']]);
        $this->servicio->guardarObservacion($reporte, $actividad, $datos['observacion']);

        return $this->volver($seguimiento, $proyecto, $reporte, 'Observación guardada.');
    }

    public function updateFocalizacion(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);

        $datos = $request->validate([
            'focalizacion' => ['required', 'array', 'min:1'],
            'focalizacion.*.municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'focalizacion.*.porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'focalizacion.*.valor' => ['nullable', 'numeric', 'min:0'],
            'focalizacion.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'justificacion_focalizacion' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->servicio->guardarFocalizacion($reporte, array_values($datos['focalizacion']), $datos['justificacion_focalizacion'] ?? null);

        return $this->volver($seguimiento, $proyecto, $reporte, 'Focalización guardada.');
    }

    public function updateDistribucion(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);

        $datos = $request->validate([
            'distribucion' => ['required', 'array', 'min:1'],
            'distribucion.*.actividad_id' => ['nullable', 'integer'],
            'distribucion.*.fuente_financiacion_id' => ['nullable', 'integer', 'exists:fuentes_financiacion,id'],
            'distribucion.*.municipio_id' => ['required', 'integer', 'exists:municipios,id'],
            'distribucion.*.programado' => ['nullable', 'numeric', 'min:0'],
            'distribucion.*.comprometido' => ['nullable', 'numeric', 'min:0'],
            'distribucion.*.obligado' => ['nullable', 'numeric', 'min:0'],
            'distribucion.*.pagado' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->servicio->guardarDistribucion($reporte, array_values($datos['distribucion']));

        return $this->volver($seguimiento, $proyecto, $reporte, 'Distribución financiera guardada.');
    }

    public function updateTrazadores(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);
        $request->merge(['trazadores' => $request->input('trazadores', [])]);
        $datos = $request->validate([
            'trazadores' => ['array'],
            'trazadores.*.proyecto_trazador_id' => ['required', 'integer'],
            'trazadores.*.trazador_categoria_id' => ['required', 'integer'],
            'trazadores.*.fuente_financiacion_id' => ['required', 'integer'],
            'trazadores.*.asignado' => ['nullable', 'numeric', 'min:0'],
            'trazadores.*.comprometido' => ['nullable', 'numeric', 'min:0'],
            'trazadores.*.obligado' => ['nullable', 'numeric', 'min:0'],
            'trazadores.*.pagado' => ['nullable', 'numeric', 'min:0'],
            'justificacion_trazadores' => ['nullable', 'string', 'max:4000'],
        ]);
        $this->servicio->guardarTrazadores($reporte, array_values($datos['trazadores']), $datos['justificacion_trazadores'] ?? null, $request->user());

        return $this->volver($seguimiento, $proyecto, $reporte, 'Distribución por trazadores confirmada.');
    }

    public function enviar(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('update', $reporte);

        $this->servicio->enviar($reporte, $request->user());

        return $this->volver($seguimiento, $proyecto, $reporte, 'Reporte enviado a la Gerencia.');
    }

    public function aprobar(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('revisar', $reporte);

        $datos = $request->validate(['observacion' => ['nullable', 'string', 'max:4000']]);
        $this->servicio->aprobar($reporte, $request->user(), $datos['observacion'] ?? null);

        return $this->volver($seguimiento, $proyecto, $reporte, 'Reporte aprobado.');
    }

    public function devolver(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): RedirectResponse
    {
        $reporte = $this->reporte($request, $seguimiento, $proyecto);
        $this->authorize('revisar', $reporte);

        $datos = $request->validate(['observacion' => ['required', 'string', 'min:5', 'max:4000']]);
        $this->servicio->devolver($reporte, $request->user(), $datos['observacion']);

        return $this->volver($seguimiento, $proyecto, $reporte, 'Reporte devuelto al sector.');
    }

    /**
     * Detalle JSON de los conteos para el modal compartido (misma forma que CountDetail).
     */
    public function detalle(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): JsonResponse
    {
        $dependencia = $this->dependencia($request, $seguimiento, $proyecto);
        $datos = $request->validate([
            'relacion' => ['required', Rule::in(['lineas', 'sin_evidencia', 'historial_techos'])],
            'techo' => ['nullable', 'integer'],
        ]);
        $relacion = $datos['relacion'];
        $techoId = $datos['techo'] ?? null;
        $reporte = ReporteProyecto::query()->where(['seguimiento_id' => $seguimiento->id, 'proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id])->first();
        $pesos = fn (mixed $valor): string => ServicioReporteSectorial::pesos($valor);

        [$titulo, $grupos] = match ($relacion) {
            'lineas' => ['Líneas de pasiva', PasivaLinea::query()
                ->where('seguimiento_id', $seguimiento->id)
                ->vigentes()
                ->where('proyecto_id', $proyecto->id)
                ->where('dependencia_id', $dependencia->id)
                ->with('fuente')
                ->orderBy('fila')
                ->get()
                ->groupBy(fn (PasivaLinea $linea): string => $linea->fuente?->etiqueta() ?? (string) $linea->codigo_fuente)
                ->map(fn ($lineas, string $fuente): array => [
                    'titulo' => $fuente,
                    'items' => $lineas->map(fn (PasivaLinea $linea): array => [
                        'codigo' => $linea->identificacion_presupuestal,
                        'nombre' => $linea->concepto,
                        'extra' => [
                            ['label' => 'Definitiva', 'valor' => $pesos($linea->apropiacion_definitiva)],
                            ['label' => 'Compromisos', 'valor' => $pesos($linea->compromisos)],
                            ['label' => 'Pagos', 'valor' => $pesos($linea->pagos)],
                        ],
                    ])->values()->all(),
                ])->values()->all()],
            'sin_evidencia' => ['Avances físicos sin evidencia', [[
                'titulo' => 'Actividades',
                'vacio' => 'Todos los avances tienen evidencia.',
                'items' => $reporte === null ? [] : $reporte->avances()->pendientesEvidencia()->with('actividad')->get()->map(fn ($avance): array => [
                    'codigo' => $avance->actividad->codigo ?: 'ACT-'.$avance->actividad->id,
                    'nombre' => $avance->actividad->nombre,
                    'extra' => [
                        ['label' => 'Cantidad', 'valor' => rtrim(rtrim(number_format((float) $avance->cantidad, 4, ',', '.'), '0'), ',').' '.$avance->actividad->unidad_medida],
                        ['label' => 'Fecha de ejecución', 'valor' => $avance->fecha_ejecucion?->format('d/m/Y') ?? '—'],
                    ],
                ])->values()->all(),
            ]]],
            'historial_techos' => ['Historial de techos', Techo::query()
                ->delSeguimiento($seguimiento)
                ->where('proyecto_id', $proyecto->id)
                ->where('dependencia_id', $dependencia->id)
                ->when($techoId, fn ($query) => $query->whereKey($techoId))
                ->with(['fuente', 'historial.usuario'])
                ->get()
                ->map(fn (Techo $techo): array => [
                    'titulo' => $techo->fuente->etiqueta(),
                    'items' => $techo->historial->map(fn ($cambio): array => [
                        'codigo' => $cambio->created_at?->format('d/m/Y H:i'),
                        'nombre' => $cambio->origen->label().': '.$cambio->motivo,
                        'extra' => [
                            ['label' => 'Valor', 'valor' => $pesos($cambio->valor_anterior).' → '.$pesos($cambio->valor_nuevo)],
                            ['label' => 'Usuario', 'valor' => $cambio->usuario?->name ?? 'Proceso de carga'],
                        ],
                    ])->values()->all(),
                ])->values()->all()],
        };

        return response()->json([
            'titulo' => $titulo,
            'registro' => ['codigo' => $proyecto->bpin, 'nombre' => $proyecto->nombre],
            'total' => collect($grupos)->sum(fn (array $grupo): int => count($grupo['items'])),
            'resumen' => [['label' => $dependencia->etiqueta(), 'valor' => $seguimiento->etiqueta()]],
            'grupos' => $grupos,
        ]);
    }

    /**
     * Resuelve la dependencia del reporte entre las que el usuario puede ver y que tienen techo en el BPIN.
     * Si no hay ninguna, el BPIN no es de su sector en este seguimiento: 404.
     */
    private function reporte(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): ReporteProyecto
    {
        return $this->servicio->reporte($seguimiento, $proyecto, $this->dependencia($request, $seguimiento, $proyecto));
    }

    private function dependencia(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): Dependencia
    {
        $dependencias = $this->servicio->dependenciasConTecho($seguimiento, $proyecto);
        abort_if($dependencias->isEmpty(), 404);

        return $dependencias->firstWhere('id', $request->integer('dependencia')) ?? $dependencias->first();
    }

    private function volver(Seguimiento $seguimiento, Proyecto $proyecto, ReporteProyecto $reporte, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $proyecto, 'dependencia' => $reporte->dependencia_id])
            ->with('status', $mensaje);
    }
}
