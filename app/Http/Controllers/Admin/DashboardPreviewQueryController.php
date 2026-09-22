<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardQueryController;
use App\Models\Dashboard;
use App\Services\Dashboards\DashboardQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPreviewQueryController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, DashboardQueryController $controller, DashboardQueryService $service): JsonResponse
    {
        abort_unless($dashboard->canEdit($request->user()) || $request->user()->canApproveDashboards(), 403);

        return $controller->respond($request, $dashboard, $dashboard->draft_config ?? [], $service, false);
    }
}
