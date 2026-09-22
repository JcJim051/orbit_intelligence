<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Services\Dashboards\BuildDashboardConfig;
use Illuminate\Http\JsonResponse;

class PublicDashboardConfigController extends Controller
{
    public function __invoke(Dashboard $dashboard, BuildDashboardConfig $builder): JsonResponse
    {
        abort_unless($dashboard->isPublished(), 404);

        return response()->json($builder->handle($dashboard))->header('Cache-Control', 'public, max-age=60');
    }
}
