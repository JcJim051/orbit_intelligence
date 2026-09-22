<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\TabularDataSource;
use App\Services\Dashboards\DashboardQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardQueryController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, DashboardQueryService $service): JsonResponse
    {
        abort_unless($dashboard->isPublished(), 404);
        $config = $dashboard->versions()->where('version', $dashboard->published_version)->first()?->config ?? [];

        return $this->respond($request, $dashboard, $config, $service, true);
    }

    /** @param array<string, mixed> $config */
    public function respond(Request $request, Dashboard $dashboard, array $config, DashboardQueryService $service, bool $public): JsonResponse
    {
        $data = $request->validate(['widget' => ['required', 'string', 'max:80'], 'filters' => ['nullable', 'array', 'max:12'], 'filters.*' => ['nullable', 'string', 'max:160']]);
        $widget = collect($config['widgets'] ?? [])->firstWhere('id', $data['widget']);
        abort_unless($widget !== null, 404);
        $filters = $data['filters'] ?? [];
        if (($widget['scope'] ?? 'global') === 'departamental_fijo') {
            unset($filters[$config['map']['join_data_field'] ?? 'codigo_dane']);
        }
        $query = $widget['query'] ?? [];
        if (($widget['type'] ?? null) === 'table') {
            $query['operation'] = 'rows';
        }
        $source = TabularDataSource::query()->findOrFail($config['data_source_id'] ?? null);
        $versionQuery = $source->currentVersion();
        $isOptimizedPopulationQuery = str_starts_with((string) ($query['operation'] ?? ''), 'population_');
        $version = ($isOptimizedPopulationQuery && $source->getConnection()->getDriverName() === 'pgsql')
            ? $versionQuery->select(['id', 'tabular_data_source_id', 'fields'])->firstOrFail()
            : $versionQuery->firstOrFail();
        $cacheKey = 'dashboard-query:'.$dashboard->id.':'.$source->current_version.':'.hash('sha256', json_encode([$query, $filters, $public]));
        $result = Cache::remember($cacheKey, now()->addMinute(), fn (): array => $service->run($version, $query, $filters, $public));

        return response()->json($result)->header('Cache-Control', $public ? 'public, max-age=60' : 'no-store');
    }
}
