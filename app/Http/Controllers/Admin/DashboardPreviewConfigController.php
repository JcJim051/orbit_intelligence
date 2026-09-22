<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Services\Dashboards\BuildDashboardConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPreviewConfigController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, BuildDashboardConfig $builder): JsonResponse
    {
        abort_unless($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards(), 403);

        return response()->json($builder->handle($dashboard, true))->header('Cache-Control', 'no-store');
    }
}
