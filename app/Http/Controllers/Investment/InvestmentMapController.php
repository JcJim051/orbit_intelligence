<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Models\InvestmentLocation;
use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InvestmentMapController extends Controller
{
    public function __invoke(Request $request, InvestmentMetricsService $metrics, GetMetaMunicipalBoundaries $boundaries): JsonResponse
    {
        $filters = $request->validate([
            'universe' => ['nullable', 'in:governor,territory,ecosystem'],
            'period_mode' => ['nullable', 'in:execution,horizon'],
        ]);
        $filters['universe'] ??= 'governor';
        $filters['period_mode'] ??= 'execution';

        try {
            $geojson = $boundaries->handle();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No fue posible consultar los límites municipales del DANE.'], 503);
        }

        $projectIds = $metrics->query($filters)->select('investment_projects.id');
        $counts = InvestmentLocation::query()
            ->where('department_code', '50')
            ->whereIn('investment_project_id', $projectIds)
            ->selectRaw('municipality_code, count(distinct investment_project_id) as projects')
            ->groupBy('municipality_code')
            ->pluck('projects', 'municipality_code');

        $geojson['features'] = collect($geojson['features'] ?? [])->map(function (array $feature) use ($counts, $filters): array {
            $code = (string) data_get($feature, 'properties.mpio_cdpmp', '');
            $feature['properties']['project_count'] = (int) ($counts[$code] ?? 0);
            $feature['properties']['projects_url'] = route('investments.projects.index', [
                'universe' => $filters['universe'],
                'period_mode' => $filters['period_mode'],
                'municipality' => $code,
            ]);

            return $feature;
        })->values()->all();

        return response()->json($geojson);
    }
}
