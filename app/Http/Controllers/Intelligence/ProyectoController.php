<?php

namespace App\Http\Controllers\Intelligence;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Proyecto;
use App\Services\Intelligence\ProyectoRelacionWorkbook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProyectoController extends Controller
{
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
            ->with(['dependencias', 'municipio'])
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
            'metasProducto' => fn ($query) => $query
                ->with([
                    'sectorMga',
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

        return view('intelligence.proyectos.show', [
            'project' => $proyecto,
            'summary' => $summary,
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
