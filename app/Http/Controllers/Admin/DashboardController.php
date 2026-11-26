<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardEngine;
use App\Enums\DashboardStatus;
use App\Enums\GeoViewerStatus;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDashboardRequest;
use App\Http\Requests\UpdateDashboardRequest;
use App\Http\Requests\UpdateSupersetDashboardRequest;
use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\TabularDataSource;
use App\Models\User;
use App\Services\Analitica\DiccionarioAnalitica;
use App\Services\AuditLogger;
use App\Services\Dashboards\PopulationDashboardTemplate;
use App\Services\Dashboards\ValidateDashboardConfig;
use App\Services\Superset\AprovisionadorDashboard;
use App\Services\Superset\SupersetClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-dashboards');
        $dashboards = Dashboard::query()->with(['owner', 'approver'])
            ->when(! $request->user()->isAdmin() && ! $request->user()->canApproveDashboards(), fn ($query) => $query->where(fn ($query) => $query->where('owner_id', $request->user()->id)->orWhereHas('collaborators', fn ($query) => $query->whereKey($request->user()->id))))
            ->orderBy('name')->get();

        $datasets = collect(DiccionarioAnalitica::vistas())->map(fn (array $dataset): array => [
            'name' => (string) $dataset['nombre'],
            'domain' => (string) $dataset['dominio'],
            'description' => (string) $dataset['descripcion'],
            'source' => (string) $dataset['fuente'],
            'cutoff' => (string) $dataset['corte'],
            'columns' => count($dataset['columnas']),
        ])->values();

        return view('admin.dashboards.index', [
            'dashboards' => $dashboards,
            'datasets' => $datasets,
            'supersetConfigured' => filled(config('superset.url')),
        ]);
    }

    public function store(StoreDashboardRequest $request, PopulationDashboardTemplate $template, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $superset = $data['template'] === 'superset_basic';
        $dashboard = Dashboard::create([
            'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'engine' => $superset ? DashboardEngine::Superset : DashboardEngine::SiidNative,
            'owner_id' => $request->user()->id, 'status' => DashboardStatus::Draft,
            'draft_config' => $data['template'] === 'population' ? $template->make() : ['theme' => ['primary' => '#047857'], 'map' => [], 'data_source_id' => null, 'global_filters' => [], 'widgets' => []],
            'wizard_config' => $superset ? ['dataset_name' => null, 'charts' => []] : null,
        ]);
        $audit->log(null, 'dashboard_created', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards');

        return redirect(Workspace::getUrl(['workspace' => 'tablero', 'record' => $dashboard->slug]))
            ->with('status', 'Dashboard creado. Configure sus datos y componentes.');
    }

    public function edit(Request $request, Dashboard $dashboard): View
    {
        abort_unless($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards(), 403);

        if ($dashboard->usesSuperset()) {
            return view('admin.dashboards.superset-edit', [
                'dashboard' => $dashboard->load('collaborators'),
                'datasets' => collect(DiccionarioAnalitica::vistas())->map(fn (array $dataset): array => [
                    'name' => (string) $dataset['nombre'],
                    'domain' => (string) $dataset['dominio'],
                    'description' => (string) $dataset['descripcion'],
                    'columns' => array_values(array_map(
                        fn (array $column): string => (string) $column['nombre'],
                        $dataset['columnas'],
                    )),
                ])->values(),
                'users' => User::query()->where('active', true)->orderBy('name')->get(),
                'supersetConfigured' => filled(config('superset.url')),
            ]);
        }

        $sources = TabularDataSource::query()->with('currentVersion')->orderBy('name')->get();
        $sources->each(function (TabularDataSource $source): void {
            $summaries = collect($source->currentVersion?->records ?? [])
                ->filter(fn (array $row): bool => filled($row['ano'] ?? null))
                ->groupBy(fn (array $row): string => (string) $row['ano'])
                ->map(fn ($rows, string $year): array => [
                    'year' => $year,
                    'rows' => $rows->count(),
                    'total_rows' => $rows->where('area_geografica', 'Total')->count(),
                ])
                ->sortByDesc('year')
                ->values()
                ->all();
            $source->setAttribute('population_years', $summaries);
        });

        return view('admin.dashboards.edit', [
            'dashboard' => $dashboard->load('collaborators'),
            'sources' => $sources,
            'geoViewers' => GeoViewer::query()
                ->where('status', GeoViewerStatus::Published)
                ->orderBy('name')
                ->get(),
            'users' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateSuperset(UpdateSupersetDashboardRequest $request, Dashboard $dashboard, AuditLogger $audit): RedirectResponse
    {
        $old = $dashboard->toArray();
        $data = $request->validated();
        $oldCharts = collect($dashboard->wizard_config['charts'] ?? [])->filter(fn (mixed $chart): bool => is_array($chart));
        $usedRemoteIds = [];
        $charts = collect($data['charts'])->map(function (array $chart, int $index) use ($oldCharts, &$usedRemoteIds): array {
            $key = filled($chart['key'] ?? null) ? (string) $chart['key'] : (string) Str::uuid();
            $previous = $oldCharts->first(fn (array $old): bool => ($old['key'] ?? null) === $key)
                ?? $oldCharts->get($index);

            if (is_array($previous) && is_numeric($previous['superset_chart_id'] ?? null)) {
                $chart['superset_chart_id'] = (int) $previous['superset_chart_id'];
                $usedRemoteIds[] = (int) $previous['superset_chart_id'];
            }

            $chart['key'] = $key;

            return $chart;
        })->values()->all();
        $retiredIds = $oldCharts
            ->pluck('superset_chart_id')
            ->filter(fn (mixed $id): bool => is_numeric($id) && ! in_array((int) $id, $usedRemoteIds, true))
            ->map(fn (mixed $id): int => (int) $id)
            ->merge((array) ($dashboard->wizard_config['retired_chart_ids'] ?? []))
            ->unique()->values()->all();

        $dashboard->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'wizard_config' => [
                'dataset_name' => $data['dataset_name'],
                'charts' => $charts,
                'retired_chart_ids' => $retiredIds,
            ],
            'status' => DashboardStatus::Draft,
            'submitted_at' => null,
            'superset_synced_at' => null,
            'superset_sync_error' => null,
        ]);

        $audit->log(null, 'superset_dashboard_wizard_updated', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards', $old, $dashboard->fresh()->toArray());

        return back()->with('status', 'Estructura del tablero guardada. Ya puede continuar con la sincronización en Superset.');
    }

    public function syncSuperset(Request $request, Dashboard $dashboard, AprovisionadorDashboard $provisioner, AuditLogger $audit): RedirectResponse
    {
        abort_unless($dashboard->usesSuperset() && $dashboard->canEdit($request->user()), 403);

        try {
            $provisioner->sincronizar($dashboard);
        } catch (\RuntimeException $exception) {
            $dashboard->update(['superset_sync_error' => $exception->getMessage(), 'superset_synced_at' => null]);

            return back()->with('error', $exception->getMessage());
        }

        $audit->log(null, 'superset_dashboard_synced', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards');

        return back()->with('status', 'Tablero sincronizado y previsualización embebida habilitada.');
    }

    public function supersetToken(Request $request, Dashboard $dashboard, SupersetClient $superset): JsonResponse
    {
        abort_unless(
            $dashboard->usesSuperset()
            && filled($dashboard->superset_embedded_uuid)
            && ($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards()),
            403,
        );

        try {
            $token = $superset->tokenInvitado((string) $dashboard->superset_embedded_uuid, [], false);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'token' => $token,
            'uuid' => $dashboard->superset_embedded_uuid,
            'superset_url' => config('superset.url'),
        ]);
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, AuditLogger $audit): JsonResponse|RedirectResponse
    {
        $old = $dashboard->toArray();
        $config = $request->validated('config');
        $config['map'] ??= [];
        $configChanged = $config != ($dashboard->draft_config ?? []);
        $dashboard->update([
            ...$request->safe()->only(['name', 'description']),
            'draft_config' => $config,
            'status' => $configChanged ? DashboardStatus::Draft : $dashboard->status,
            'submitted_at' => $configChanged ? null : $dashboard->submitted_at,
        ]);
        $audit->log(null, 'dashboard_draft_updated', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards', $old, $dashboard->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
        }

        return back()->with('status', 'Borrador guardado.');
    }

    public function submit(Request $request, Dashboard $dashboard, AuditLogger $audit, ValidateDashboardConfig $validator): RedirectResponse
    {
        abort_unless($dashboard->canEdit($request->user()), 403);
        $errors = $dashboard->usesSuperset()
            ? ($dashboard->superset_synced_at && $dashboard->superset_embedded_uuid ? [] : ['Sincronice el tablero con Superset antes de enviarlo a revisión.'])
            : $validator->errors($dashboard->draft_config ?? []);
        if ($errors !== []) {
            return back()->with('error', implode(' ', $errors));
        }
        $dashboard->update(['status' => DashboardStatus::PendingReview, 'submitted_at' => now()]);
        $audit->log(null, 'dashboard_submitted', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards');

        return back()->with('status', 'Dashboard enviado a revisión.');
    }

    public function collaborators(Request $request, Dashboard $dashboard): RedirectResponse
    {
        abort_unless($dashboard->canEdit($request->user()), 403);
        $data = $request->validate(['user_ids' => ['nullable', 'array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $dashboard->collaborators()->sync(collect($data['user_ids'] ?? [])->reject(fn ($id) => (int) $id === $dashboard->owner_id)->mapWithKeys(fn ($id) => [$id => ['permission' => 'edit']])->all());

        return back()->with('status', 'Colaboradores actualizados.');
    }
}
