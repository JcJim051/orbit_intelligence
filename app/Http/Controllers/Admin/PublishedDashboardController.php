<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardStatus;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Services\AuditLogger;
use App\Services\Dashboards\ValidateDashboardConfig;
use App\Services\Superset\AprovisionadorDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishedDashboardController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, AuditLogger $audit, ValidateDashboardConfig $validator, AprovisionadorDashboard $provisioner): RedirectResponse
    {
        Gate::authorize('approve-dashboards');
        if ($dashboard->status !== DashboardStatus::PendingReview) {
            return back()->with('error', 'El dashboard cambió después de enviarse a revisión. Envíelo nuevamente a revisión antes de publicarlo.');
        }
        $errors = $dashboard->usesSuperset()
            ? ($dashboard->superset_synced_at && $dashboard->superset_embedded_uuid ? [] : ['Sincronice el tablero con Superset antes de publicarlo.'])
            : $validator->errors($dashboard->draft_config ?? []);
        if ($errors !== []) {
            return back()->with('error', implode(' ', $errors));
        }

        if ($dashboard->usesSuperset()) {
            try {
                $provisioner->publicar($dashboard);
            } catch (\RuntimeException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        DB::transaction(function () use ($dashboard, $request): void {
            $version = ((int) $dashboard->versions()->max('version')) + 1;
            $config = $dashboard->usesSuperset() ? [
                'engine' => 'superset',
                'wizard' => $dashboard->wizard_config,
                'superset_dashboard_id' => $dashboard->superset_dashboard_id,
                'superset_embedded_uuid' => $dashboard->superset_embedded_uuid,
            ] : $dashboard->draft_config;
            $dashboard->versions()->create(['version' => $version, 'config' => $config, 'created_by' => $dashboard->owner_id, 'approved_by' => $request->user()->id, 'published_at' => now()]);
            $dashboard->update(['status' => DashboardStatus::Published, 'published_version' => $version, 'published_at' => now(), 'approved_by' => $request->user()->id]);
        });
        $audit->log(null, 'dashboard_published', $request->user(), ['dashboard_id' => $dashboard->id, 'version' => $dashboard->published_version], 'dashboards');

        return back()->with('status', 'Dashboard publicado. La versión pública quedó protegida contra cambios.');
    }
}
