<?php

namespace App\Http\Controllers\Intelligence;

use App\Enums\TipoFocalizacion;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\MetaProducto;
use App\Models\Municipio;
use App\Models\PoliticaPublicaDepartamental;
use App\Models\Proyecto;
use App\Models\ProyectoTrazador;
use App\Models\Trazador;
use App\Models\TrazadorCategoria;
use App\Services\Intelligence\ProyectoRelacionWorkbook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProyectoController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', Proyecto::class);

        return view('intelligence.proyectos.form', [
            'project' => new Proyecto(['activo' => true]),
            'dependenciasSeleccionadas' => collect(),
            'metasSeleccionadas' => collect(),
            'metasRegionalizacionSeleccionadas' => collect(),
            'dependencias' => Dependencia::query()->where('activo', true)->orderBy('codigo')->get(),
            'metasProducto' => MetaProducto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'municipios' => Municipio::query()->where('activo', true)->orderBy('nombre')->get(),
            'municipiosSeleccionados' => collect(),
            'focalizaciones' => TipoFocalizacion::options(),
            'trazadores' => Trazador::query()->where('activo', true)->with(['categorias' => fn ($query) => $query->where('activo', true)->orderBy('nombre')])->orderBy('codigo')->get(),
            'politicasPublicas' => PoliticaPublicaDepartamental::query()->where('activo', true)->orderBy('nombre')->get(),
            'trazadoresSeleccionados' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Proyecto::class);
        $validated = $this->validateProject($request);
        $project = DB::transaction(function () use ($validated, $request): Proyecto {
            $project = Proyecto::query()->create(collect($validated)->except(['dependencia_ids', 'meta_producto_ids', 'municipio_ids', 'trazadores'])->all());
            $project->dependencias()->sync($validated['dependencia_ids'] ?? []);
            $this->syncMetasProducto($project, $validated, $request->user()->id);
            $project->municipiosRegionalizacion()->sync($validated['municipio_ids'] ?? []);
            $this->syncTrazadores($project, $validated['trazadores'] ?? [], $request->user()->id);

            return $project;
        });

        return redirect(Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $project->getRouteKey()]))
            ->with('status', 'Proyecto creado correctamente.');
    }

    public function edit(Request $request, Proyecto $proyecto): View
    {
        $this->authorize('update', $proyecto);
        $proyecto->load('dependencias');
        $proyecto->load(['metasProducto', 'municipiosRegionalizacion', 'trazadores.categorias']);

        return view('intelligence.proyectos.form', [
            'project' => $proyecto,
            'dependenciasSeleccionadas' => $proyecto->dependencias->pluck('id'),
            'metasSeleccionadas' => $proyecto->metasProducto->pluck('id'),
            'metasRegionalizacionSeleccionadas' => $proyecto->metasProducto->where('pivot.regionaliza', true)->pluck('id'),
            'dependencias' => Dependencia::query()->where('activo', true)->orderBy('codigo')->get(),
            'metasProducto' => MetaProducto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'municipios' => Municipio::query()->where('activo', true)->orderBy('nombre')->get(),
            'municipiosSeleccionados' => $proyecto->municipiosRegionalizacion->pluck('id'),
            'focalizaciones' => TipoFocalizacion::options(),
            'trazadores' => Trazador::query()->where('activo', true)->with(['categorias' => fn ($query) => $query->where('activo', true)->orderBy('nombre')])->orderBy('codigo')->get(),
            'politicasPublicas' => PoliticaPublicaDepartamental::query()->where('activo', true)->orderBy('nombre')->get(),
            'trazadoresSeleccionados' => $proyecto->trazadores->keyBy('trazador_id'),
        ]);
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $this->authorize('update', $proyecto);
        $validated = $this->validateProject($request, $proyecto);
        DB::transaction(function () use ($proyecto, $validated, $request): void {
            $proyecto->update(collect($validated)->except(['dependencia_ids', 'meta_producto_ids', 'municipio_ids', 'trazadores'])->all());
            $proyecto->dependencias()->sync($validated['dependencia_ids'] ?? []);
            $this->syncMetasProducto($proyecto, $validated, $request->user()->id);
            $proyecto->municipiosRegionalizacion()->sync($validated['municipio_ids'] ?? []);
            $this->syncTrazadores($proyecto, $validated['trazadores'] ?? [], $request->user()->id);
        });

        return redirect(Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->getRouteKey()]))
            ->with('status', 'Proyecto actualizado correctamente.');
    }

    public function destroy(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $this->authorize('delete', $proyecto);
        $proyecto->delete();

        return redirect(Workspace::getUrl(['workspace' => 'metas-proyectos']))
            ->with('status', 'Proyecto eliminado.');
    }

    /** @return array<string, mixed> */
    private function validateProject(Request $request, ?Proyecto $project = null): array
    {
        return $request->validate([
            'bpin' => ['required', 'string', 'max:20', Rule::unique('proyectos', 'bpin')->ignore($project?->id)],
            'nombre' => ['required', 'string', 'max:500'],
            'tipo_focalizacion' => ['nullable', 'string', Rule::in(array_keys(TipoFocalizacion::options()))],
            'regionalizacion_modo' => ['nullable', Rule::in(array_keys(Proyecto::modosRegionalizacion()))],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'municipio_ids' => ['nullable', 'array'],
            'municipio_ids.*' => ['integer', 'distinct', 'exists:municipios,id'],
            'activo' => ['nullable', 'boolean'],
            'dependencia_ids' => ['nullable', 'array'],
            'dependencia_ids.*' => ['integer', 'distinct', 'exists:dependencias,id'],
            'meta_producto_ids' => ['nullable', 'array'],
            'meta_producto_ids.*' => ['integer', 'distinct', 'exists:metas_producto,id'],
            'regionalizacion_meta_ids' => ['nullable', 'array'],
            'regionalizacion_meta_ids.*' => ['integer', 'distinct', 'exists:metas_producto,id'],
            'trazadores' => ['nullable', 'array'],
            'trazadores.*.activo' => ['nullable', 'boolean'],
            'trazadores.*.trazador_id' => ['required_with:trazadores.*.activo', 'integer', 'distinct', 'exists:trazadores,id'],
            'trazadores.*.politica_publica_departamental_id' => ['nullable', 'integer', 'exists:politicas_publicas_departamentales,id'],
            'trazadores.*.categoria_ids' => ['nullable', 'array'],
            'trazadores.*.categoria_ids.*' => ['integer', 'distinct', 'exists:trazador_categorias,id'],
        ]) + [
            'activo' => $request->boolean('activo'),
            'municipio_ids' => $this->municipiosDelRequest($request),
            'regionalizacion_modo' => $request->input('regionalizacion_modo', Proyecto::REGIONALIZACION_PROYECTO),
            'regionalizacion_meta_ids' => collect($request->input('regionalizacion_meta_ids', []))->map(fn ($id): int => (int) $id)->all(),
        ];
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function syncTrazadores(Proyecto $project, array $filas, int $userId): void
    {
        DB::transaction(function () use ($project, $filas, $userId): void {
            $seleccionados = collect($filas)->filter(fn (array $fila): bool => filter_var($fila['activo'] ?? false, FILTER_VALIDATE_BOOLEAN));
            $idsActivos = [];

            foreach ($seleccionados as $indice => $fila) {
                $trazadorId = (int) $fila['trazador_id'];
                $categoriaIds = collect($fila['categoria_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
                $validas = TrazadorCategoria::query()->where('trazador_id', $trazadorId)->whereIn('id', $categoriaIds)->pluck('id');
                if ($categoriaIds->isEmpty() || $validas->count() !== $categoriaIds->count()) {
                    throw ValidationException::withMessages(["trazadores.{$indice}.categoria_ids" => 'Seleccione al menos una categoría válida del trazador.']);
                }

                $asociacion = ProyectoTrazador::query()->firstOrNew(['proyecto_id' => $project->id, 'trazador_id' => $trazadorId]);
                $asociacion->fill([
                    'politica_publica_departamental_id' => $fila['politica_publica_departamental_id'] ?: null,
                    'activo' => true,
                    'updated_by' => $userId,
                ]);
                $asociacion->created_by ??= $userId;
                $asociacion->save();
                $idsActivos[] = $asociacion->id;
                $asociacion->categorias()->whereNotIn('trazador_categoria_id', $validas)->update(['activo' => false]);
                foreach ($validas as $categoriaId) {
                    $asociacion->categorias()->updateOrCreate(['trazador_categoria_id' => $categoriaId], ['activo' => true]);
                }
            }

            $project->trazadores()->when($idsActivos !== [], fn ($query) => $query->whereNotIn('id', $idsActivos))->update(['activo' => false, 'updated_by' => $userId]);
            $project->reportes()->whereHas('seguimiento', fn ($query) => $query->where('estado', '!=', 'cerrado'))->update([
                'trazadores_confirmados_at' => null,
                'trazadores_confirmados_por' => null,
            ]);
        });
    }

    /** @param array<string, mixed> $validated */
    private function syncMetasProducto(Proyecto $project, array $validated, int $userId): void
    {
        $metaIds = collect($validated['meta_producto_ids'] ?? [])->map(fn ($id): int => (int) $id)->values();
        $regionalizadas = collect($validated['regionalizacion_meta_ids'] ?? [])->map(fn ($id): int => (int) $id);
        $modo = $validated['regionalizacion_modo'] ?? Proyecto::REGIONALIZACION_PROYECTO;
        $sync = $metaIds->mapWithKeys(fn (int $id): array => [$id => ['regionaliza' => $modo === Proyecto::REGIONALIZACION_META && $regionalizadas->contains($id)]])->all();
        $project->metasProducto()->sync($sync);

        $dependenciaIds = $project->dependencias()->pluck('dependencias.id');
        if ($metaIds->isEmpty() || $dependenciaIds->isEmpty()) {
            return;
        }

        $metas = MetaProducto::query()
            ->whereIn('id', $metaIds)
            ->with(['indicadorProducto', 'planIndicativo'])
            ->get();

        foreach ($dependenciaIds as $dependenciaId) {
            foreach ($metas as $meta) {
                $actividad = Actividad::query()
                    ->withoutGlobalScopes()
                    ->where('proyecto_id', $project->id)
                    ->where('dependencia_id', $dependenciaId)
                    ->where('meta_producto_id', $meta->id)
                    ->where('origen', Actividad::ORIGEN_CONSOLIDADO_META)
                    ->first();

                if ($actividad !== null) {
                    if ($actividad->trashed() || ! $actividad->activo) {
                        $actividad->forceFill(['deleted_at' => null, 'activo' => true])->save();
                    }

                    continue;
                }

                $plan = $meta->planIndicativo->firstWhere('vigencia', now()->year)
                    ?? $meta->planIndicativo->sortByDesc('vigencia')->first();

                Actividad::query()->create([
                    'proyecto_id' => $project->id,
                    'dependencia_id' => (int) $dependenciaId,
                    'meta_producto_id' => $meta->id,
                    'codigo' => 'META-'.$meta->codigo,
                    'nombre' => 'Avance consolidado de la meta producto',
                    'unidad_medida' => $meta->indicadorProducto?->unidad_medida,
                    'cantidad_programada' => (float) ($plan?->valor_programado ?? 0),
                    'origen' => Actividad::ORIGEN_CONSOLIDADO_META,
                    'activo' => true,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    /** @return list<int> */
    private function municipiosDelRequest(Request $request): array
    {
        $ids = collect($request->input('municipio_ids', []))->map(fn ($id): int => (int) $id)->filter()->values();
        $tipo = $request->string('tipo_focalizacion')->toString();
        $municipio = (int) $request->input('municipio_id', 0);

        if ($tipo === TipoFocalizacion::Municipal->value && $municipio > 0) {
            return [$municipio];
        }

        return $ids->all();
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->canAccessManagementGoals() ?? false, 403);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'dependencia' => $request->integer('dependencia') ?: null,
            'estado' => (string) $request->query('estado', ''),
        ];
        $visibleDependenciaIds = $request->user()->veTodosLosSectores()
            ? null
            : $request->user()->dependenciaIdsAsignadas();

        $projects = Proyecto::query()
            ->with(['dependencias', 'municipio', 'municipiosRegionalizacion'])
            ->withCount(['metasProducto', 'actividades', 'reportes'])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = mb_strtolower($filters['q']);
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(bpin) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('dependencias', fn (Builder $dependencias) => $dependencias
                            ->whereRaw('LOWER(nombre) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(sigla) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(codigo) LIKE ?', ['%'.$search.'%']))
                        ->orWhereHas('metasProducto', fn (Builder $metas) => $metas
                            ->whereRaw('LOWER(codigo) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->when($filters['dependencia'], fn (Builder $query, int $dependenciaId) => $query
                ->whereHas('dependencias', fn (Builder $dependencias) => $dependencias->whereKey($dependenciaId)))
            ->when($filters['estado'] === 'activo', fn (Builder $query) => $query->where('activo', true))
            ->when($filters['estado'] === 'inactivo', fn (Builder $query) => $query->where('activo', false))
            ->orderByRaw("regexp_replace(bpin, '\\D', '', 'g')::numeric desc")
            ->orderByDesc('id')
            ->get();

        $dependencias = Dependencia::query()
            ->whereHas('proyectos')
            ->when($visibleDependenciaIds !== null, fn (Builder $query) => $query->whereIn('id', $visibleDependenciaIds))
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get();

        return view('intelligence.proyectos.index', [
            'projects' => $projects,
            'dependencias' => $dependencias,
            'filters' => $filters,
        ]);
    }

    public function show(Proyecto $proyecto): View
    {
        $this->authorize('view', $proyecto);
        $user = request()->user();
        $visibleDependenciaIds = $user->veTodosLosSectores() ? null : $user->dependenciaIdsAsignadas();

        $proyecto->load([
            'dependencias',
            'municipio',
            'municipiosRegionalizacion',
            'trazadores' => fn ($query) => $query->with(['trazador', 'politicaPublica', 'categorias.categoria'])->orderBy('trazador_id'),
            'metasProducto' => fn ($query) => $query
                ->with([
                    'sectorMga',
                    'tipologiaInversion',
                    'subprograma.programa.linea.eje.pilar',
                    'metaResultado.indicador',
                ])
                ->orderBy('codigo'),
            'actividades' => fn ($query) => $query
                ->with(['dependencia', 'metaProducto'])
                ->when($visibleDependenciaIds !== null, fn ($query) => $query->whereIn('dependencia_id', $visibleDependenciaIds))
                ->orderBy('codigo')
                ->orderBy('nombre'),
            'techos' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->with(['seguimiento', 'dependencia', 'fuente'])
                ->when($visibleDependenciaIds !== null, fn ($query) => $query->whereIn('dependencia_id', $visibleDependenciaIds))
                ->latest('seguimiento_id')
                ->latest('id')
                ->limit(50),
            'reportes' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->with(['seguimiento', 'dependencia'])
                ->when($visibleDependenciaIds !== null, fn ($query) => $query->whereIn('dependencia_id', $visibleDependenciaIds))
                ->latest('seguimiento_id')
                ->latest('id')
                ->limit(50),
        ]);

        $summary = [
            'metas' => $proyecto->metasProducto->count(),
            'actividades' => $proyecto->actividades->count(),
            'techo' => $proyecto->techos->sum(fn ($techo) => (float) $techo->valor),
            'reportes' => $proyecto->reportes->count(),
        ];

        $tipologiasTotal = $proyecto->metasProducto->count();
        $tipologias = $proyecto->metasProducto
            ->groupBy(fn ($meta) => $meta->tipologia_inversion_id ?: 'sin_clasificar')
            ->map(function ($metas, $key) use ($tipologiasTotal): array {
                $tipologia = $metas->first()->tipologiaInversion;

                return [
                    'codigo' => $tipologia?->codigo,
                    'nombre' => $tipologia?->nombre ?? 'Sin clasificar',
                    'cantidad' => $metas->count(),
                    'porcentaje' => $tipologiasTotal > 0 ? ($metas->count() / $tipologiasTotal) * 100 : 0,
                ];
            })
            ->sortBy(fn (array $row): string => $row['codigo'] ?? 'ZZZ')
            ->values();

        return view('intelligence.proyectos.show', [
            'project' => $proyecto,
            'summary' => $summary,
            'tipologias' => $tipologias,
            'projectsUrl' => Workspace::getUrl(['workspace' => 'metas-proyectos']),
        ]);
    }

    public function importView(Request $request): View
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        return view('intelligence.proyectos.import-relations', [
            'projectsUrl' => Workspace::getUrl(['workspace' => 'metas-proyectos']),
            'metasProductoUrl' => Workspace::getUrl(['workspace' => 'metas-producto']),
        ]);
    }

    public function relationsTemplate(Request $request, ProyectoRelacionWorkbook $workbook): BinaryFileResponse
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        $path = storage_path('app/plantilla-relaciones-proyectos-'.uniqid().'.xlsx');
        $workbook->writeTemplate($path);

        return response()
            ->download($path, 'plantilla-relaciones-proyectos.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function importRelations(Request $request, ProyectoRelacionWorkbook $workbook): RedirectResponse
    {
        abort_unless($request->user()?->canManageIntelligenceCatalogs() ?? false, 403);

        $request->validate([
            'archivo_relaciones' => ['required', 'file', 'extensions:xlsx', 'max:10240'],
        ], [
            'archivo_relaciones.required' => 'Seleccione la matriz diligenciada.',
            'archivo_relaciones.extensions' => 'El archivo debe ser un libro .xlsx.',
            'archivo_relaciones.max' => 'El archivo no puede superar 10 MB.',
        ]);

        $result = $workbook->import($request->file('archivo_relaciones')->getPathname());

        if ($result['errores'] !== []) {
            return back()->withErrors([
                'relaciones' => 'Se encontraron errores en la carga. Corrija la matriz y vuelva a cargarla.',
                ...collect($result['errores'])
                    ->take(40)
                    ->mapWithKeys(fn (string $error, int $index): array => ['relacion_'.$index => $error])
                    ->all(),
            ]);
        }

        return back()->with('status', sprintf(
            'Matriz de proyectos importada: %d dependencia(s) vinculada(s), %d meta(s) vinculada(s), %d actividad(es) creada(s), %d actividad(es) actualizada(s), %d proyecto(s) creado(s) y %d relación(es) ya existentes.',
            $result['dependencias_vinculadas'],
            $result['metas_vinculadas'],
            $result['actividades_creadas'],
            $result['actividades_actualizadas'],
            $result['proyectos_creados'],
            $result['existentes'],
        ));
    }
}
