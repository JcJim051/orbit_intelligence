<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDashboardRequest;
use App\Http\Requests\UpdateDashboardRequest;
use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\TabularDataSource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Dashboards\PopulationDashboardTemplate;
use App\Services\Dashboards\ValidateDashboardConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-dashboards');
        $dashboards = Dashboard::query()->with(['owner', 'approver'])
            ->when(! $request->user()->isAdmin() && ! $request->user()->canApproveDashboards(), fn ($query) => $query->where(fn ($query) => $query->where('owner_id', $request->user()->id)->orWhereHas('collaborators', fn ($query) => $query->whereKey($request->user()->id))))
            ->orderBy('name')->get();

        return view('admin.dashboards.index', compact('dashboards'));
    }

    public function store(StoreDashboardRequest $request, PopulationDashboardTemplate $template, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $dashboard = Dashboard::create([
            'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'owner_id' => $request->user()->id, 'status' => DashboardStatus::Draft,
            'draft_config' => $data['template'] === 'population' ? $template->make() : ['theme' => ['primary' => '#047857'], 'map' => [], 'data_source_id' => null, 'global_filters' => [], 'widgets' => []],
        ]);
        $audit->log(null, 'dashboard_created', $request->user(), ['dashboard_id' => $dashboard->id], 'dashboards');

        return redirect()->route('admin.dashboards.edit', $dashboard)->with('status', 'Dashboard creado. Configure sus datos y componentes.');
    }

    public function edit(Request $request, Dashboard $dashboard): View
    {
        abort_unless($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards(), 403);

        return view('admin.dashboards.edit', [
            'dashboard' => $dashboard->load('collaborators'),
            'sources' => TabularDataSource::query()->with('currentVersion')->orderBy('name')->get(),
            'geoViewers' => GeoViewer::query()->orderBy('name')->get(),
            'users' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, AuditLogger $audit): JsonResponse|RedirectResponse
    {
        $old = $dashboard->toArray();
        $dashboard->update([
            ...$request->safe()->only(['name', 'description']),
            'draft_config' => $request->validated('config'),
            'status' => $dashboard->isPublished() ? DashboardStatus::Draft : $dashboard->status,
            'submitted_at' => null,
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
        $errors = $validator->errors($dashboard->draft_config ?? []);
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
