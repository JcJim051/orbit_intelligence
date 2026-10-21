<?php

namespace App\Http\Controllers\Intelligence;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Enums\TipoFocalizacion;
use App\Models\Dependencia;
use App\Models\Municipio;
use App\Models\MetaProducto;
use App\Models\Proyecto;
use App\Services\Intelligence\ProyectoRelacionWorkbook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Proyecto::class);
        $validated = $this->validateProject($request);
        $project = Proyecto::query()->create(collect($validated)->except(['dependencia_ids', 'meta_producto_ids', 'municipio_ids'])->all());
        $project->dependencias()->sync($validated['dependencia_ids'] ?? []);
        $this->syncMetasProducto($project, $validated);
        $project->municipiosRegionalizacion()->sync($validated['municipio_ids'] ?? []);

        return redirect(Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $project->getRouteKey()]))
            ->with('status', 'Proyecto creado correctamente.');
    }

    public function edit(Request $request, Proyecto $proyecto): View
    {
        $this->authorize('update', $proyecto);
        $proyecto->load('dependencias');
        $proyecto->load(['metasProducto', 'municipiosRegionalizacion']);

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
        ]);
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $this->authorize('update', $proyecto);
        $validated = $this->validateProject($request, $proyecto);
        $proyecto->update(collect($validated)->except(['dependencia_ids', 'meta_producto_ids', 'municipio_ids'])->all());
        $proyecto->dependencias()->sync($validated['dependencia_ids'] ?? []);
        $this->syncMetasProducto($proyecto, $validated);
        $proyecto->municipiosRegionalizacion()->sync($validated['municipio_ids'] ?? []);

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
        ]) + [
            'activo' => $request->boolean('activo'),
            'municipio_ids' => $this->municipiosDelRequest($request),
            'regionalizacion_modo' => $request->input('regionalizacion_modo', Proyecto::REGIONALIZACION_PROYECTO),
            'regionalizacion_meta_ids' => collect($request->input('regionalizacion_meta_ids', []))->map(fn ($id): int => (int) $id)->all(),
        ];
    }

    /** @param array<string, mixed> $validated */
    private function syncMetasProducto(Proyecto $project, array $validated): void
    {
        $metaIds = collect($validated['meta_producto_ids'] ?? [])->map(fn ($id): int => (int) $id)->values();
        $regionalizadas = collect($validated['regionalizacion_meta_ids'] ?? [])->map(fn ($id): int => (int) $id);
        $modo = $validated['regionalizacion_modo'] ?? Proyecto::REGIONALIZACION_PROYECTO;
        $sync = $metaIds->mapWithKeys(fn (int $id): array => [$id => ['regionaliza' => $modo === Proyecto::REGIONALIZACION_META && $regionalizadas->contains($id)]])->all();
        $project->metasProducto()->sync($sync);
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
