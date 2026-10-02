<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class InvestmentMapController extends Controller
{
    public function __invoke(Request $request, InvestmentMetricsService $metrics, GetMetaMunicipalBoundaries $boundaries): JsonResponse|RedirectResponse
    {
        if (! $request->wantsJson()) {
            return redirect()->route('investments.dashboard', $request->query());
        }

        $filters = $request->validate([
            'universe' => ['nullable', 'in:governor,territory,ecosystem'],
            'period_mode' => ['nullable', 'in:execution,horizon'],
            'year' => ['nullable', 'integer'],
            'sector' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'entity' => ['nullable', 'string', 'max:500'],
            'project_type' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:10'],
            'funding_source' => ['nullable', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $filters['universe'] ??= 'governor';
        $filters['period_mode'] ??= 'execution';

        try {
            $geojson = $boundaries->handle();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No fue posible consultar los límites municipales del DANE.'], 503);
        }

        $municipalSummary = $metrics->municipalSummary($filters)->keyBy('municipality_code');

        $geojson['features'] = collect($geojson['features'] ?? [])->map(function (array $feature) use ($municipalSummary, $filters): array {
            $code = (string) data_get($feature, 'properties.mpio_cdpmp', '');
            $summary = $municipalSummary->get($code);
            $feature['properties']['project_count'] = (int) ($summary?->projects ?? 0);
            $feature['properties']['total_value'] = $summary?->total_value !== null ? (float) $summary->total_value : null;
            $feature['properties']['current_value'] = $summary?->current_value !== null ? (float) $summary->current_value : null;
            $feature['properties']['committed_value'] = $summary?->committed_value !== null ? (float) $summary->committed_value : null;
            $feature['properties']['obligated_value'] = $summary?->obligated_value !== null ? (float) $summary->obligated_value : null;
            $feature['properties']['paid_value'] = $summary?->paid_value !== null ? (float) $summary->paid_value : null;
            $feature['properties']['financial_execution_percent'] = $summary?->financial_execution_percent;
            $feature['properties']['physical_progress_percent'] = $summary?->physical_progress_percent !== null ? (float) $summary->physical_progress_percent : null;
            $feature['properties']['projects_url'] = route('investments.projects.index', [
                'universe' => $filters['universe'],
                'period_mode' => $filters['period_mode'],
                'municipality' => $code,
            ] + collect($filters)->except(['municipality'])->filter(fn ($value): bool => $value !== null && $value !== '')->all());

            return $feature;
        })->values()->all();

        return response()->json($geojson);
    }
}
