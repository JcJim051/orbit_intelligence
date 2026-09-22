<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardStatus;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Services\AuditLogger;
use App\Services\Dashboards\ValidateDashboardConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishedDashboardController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, AuditLogger $audit, ValidateDashboardConfig $validator): RedirectResponse
    {
        Gate::authorize('approve-dashboards');
        abort_unless($dashboard->status === DashboardStatus::PendingReview, 409, 'El dashboard debe estar pendiente de revisión.');
        $errors = $validator->errors($dashboard->draft_config ?? []);
        if ($errors !== []) {
            return back()->with('error', implode(' ', $errors));
        }

        DB::transaction(function () use ($dashboard, $request): void {
            $version = ((int) $dashboard->versions()->max('version')) + 1;
            $dashboard->versions()->create(['version' => $version, 'config' => $dashboard->draft_config, 'created_by' => $dashboard->owner_id, 'approved_by' => $request->user()->id, 'published_at' => now()]);
            $dashboard->update(['status' => DashboardStatus::Published, 'published_version' => $version, 'published_at' => now(), 'approved_by' => $request->user()->id]);
        });
        $audit->log(null, 'dashboard_published', $request->user(), ['dashboard_id' => $dashboard->id, 'version' => $dashboard->published_version], 'dashboards');

        return back()->with('status', 'Dashboard publicado. La versión pública quedó protegida contra cambios.');
    }
}
