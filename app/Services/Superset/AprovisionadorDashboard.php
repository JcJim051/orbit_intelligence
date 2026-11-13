<?php

namespace App\Services\Superset;

use App\Models\Dashboard;
use Illuminate\Support\Str;
use RuntimeException;

class AprovisionadorDashboard
{
    public function __construct(
        private readonly SupersetClient $superset,
        private readonly SincronizadorDatasets $datasets,
    ) {}

    public function sincronizar(Dashboard $dashboard): Dashboard
    {
        if (! $dashboard->usesSuperset()) {
            throw new RuntimeException('Este tablero no utiliza Superset.');
        }

        $wizard = $dashboard->wizard_config ?? [];
        $datasetName = (string) ($wizard['dataset_name'] ?? '');
        $charts = array_values((array) ($wizard['charts'] ?? []));

        if ($datasetName === '' || $charts === []) {
            throw new RuntimeException('Seleccione un dataset y configure al menos un gráfico antes de sincronizar.');
        }

        $datasetId = $this->datasets->resolverDataset($datasetName);
        $dashboardId = $this->sincronizarDashboard($dashboard);
        $syncedCharts = [];

        foreach ($charts as $index => $chart) {
            if (! is_array($chart)) {
                continue;
            }

            $chart['key'] ??= (string) Str::uuid();
            $payload = $this->chartPayload($chart, $datasetId, $dashboardId);
            $chartId = $chart['superset_chart_id'] ?? null;

            if (is_numeric($chartId)) {
                $this->superset->request('put', '/api/v1/chart/'.(int) $chartId, $payload);
            } else {
                $response = $this->superset->request('post', '/api/v1/chart/', $payload);
                $chartId = $this->idFrom($response, 'gráfico');
            }

            $chart['superset_chart_id'] = (int) $chartId;
            $syncedCharts[] = $chart;

            $dashboard->update([
                'superset_dashboard_id' => $dashboardId,
                'superset_dataset_id' => $datasetId,
                'wizard_config' => [
                    ...$wizard,
                    'charts' => [...$syncedCharts, ...array_slice($charts, $index + 1)],
                ],
            ]);
        }

        foreach (array_unique(array_map('intval', (array) ($wizard['retired_chart_ids'] ?? []))) as $retiredId) {
            if ($retiredId > 0) {
                $this->superset->request('delete', '/api/v1/chart/'.$retiredId);
            }
        }

        $this->superset->request('put', '/api/v1/dashboard/'.$dashboardId, [
            ...$this->dashboardPayload($dashboard),
            'position_json' => json_encode($this->positions($syncedCharts), JSON_THROW_ON_ERROR),
        ]);

        $embeddedUuid = $this->habilitarEmbebido($dashboardId, $dashboard->superset_embedded_uuid);

        $dashboard->update([
            'superset_dashboard_id' => $dashboardId,
            'superset_embedded_uuid' => $embeddedUuid,
            'superset_dataset_id' => $datasetId,
            'wizard_config' => [
                'dataset_name' => $datasetName,
                'charts' => $syncedCharts,
                'retired_chart_ids' => [],
            ],
            'superset_synced_at' => now(),
            'superset_sync_error' => null,
        ]);

        return $dashboard->fresh();
    }

    public function publicar(Dashboard $dashboard): void
    {
        if (! $dashboard->usesSuperset() || ! $dashboard->superset_dashboard_id || ! $dashboard->superset_synced_at) {
            throw new RuntimeException('Sincronice el tablero con Superset antes de publicarlo.');
        }

        $this->superset->request('put', '/api/v1/dashboard/'.$dashboard->superset_dashboard_id, [
            'dashboard_title' => $dashboard->name,
            'slug' => 'siid-'.$dashboard->slug,
            'description' => $dashboard->description,
            'published' => true,
        ]);
    }

    private function sincronizarDashboard(Dashboard $dashboard): int
    {
        if ($dashboard->superset_dashboard_id) {
            $this->superset->request('put', '/api/v1/dashboard/'.$dashboard->superset_dashboard_id, [
                'dashboard_title' => $dashboard->name,
                'slug' => 'siid-'.$dashboard->slug,
                'description' => $dashboard->description,
                'published' => false,
            ]);

            return (int) $dashboard->superset_dashboard_id;
        }

        $response = $this->superset->request('post', '/api/v1/dashboard/', $this->dashboardPayload($dashboard));
        $id = $this->idFrom($response, 'tablero');
        $dashboard->update(['superset_dashboard_id' => $id]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(Dashboard $dashboard): array
    {
        return [
            'dashboard_title' => $dashboard->name,
            'slug' => 'siid-'.$dashboard->slug,
            'description' => $dashboard->description,
            'published' => false,
            'position_json' => json_encode([], JSON_THROW_ON_ERROR),
            'json_metadata' => json_encode([
                'color_scheme' => 'supersetColors',
                'cross_filters_enabled' => true,
                'native_filter_configuration' => [],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private function chartPayload(array $chart, int $datasetId, int $dashboardId): array
    {
        $params = $this->chartParams($chart, $datasetId);

        return [
            'slice_name' => (string) $chart['title'],
            'description' => 'Creado desde el constructor básico del SIID.',
            'viz_type' => $params['viz_type'],
            'datasource_id' => $datasetId,
            'datasource_type' => 'table',
            'dashboards' => [$dashboardId],
            'params' => json_encode($params, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private function chartParams(array $chart, int $datasetId): array
    {
        $type = (string) ($chart['type'] ?? 'table');
        $dimension = filled($chart['dimension'] ?? null) ? (string) $chart['dimension'] : null;
        $metricColumn = (string) $chart['metric'];
        $aggregation = (string) $chart['aggregation'];
        $metric = [
            'expressionType' => 'SIMPLE',
            'column' => ['column_name' => $metricColumn],
            'aggregate' => $aggregation,
            'sqlExpression' => null,
            'label' => $aggregation.'('.$metricColumn.')',
            'optionName' => 'metric_'.substr(hash('sha256', $aggregation.'|'.$metricColumn), 0, 12),
        ];
        $vizType = match ($type) {
            'big_number' => 'big_number_total',
            'bar' => 'echarts_timeseries_bar',
            'line' => 'echarts_timeseries_line',
            'pie' => 'pie',
            default => 'table',
        };

        $base = [
            'datasource' => $datasetId.'__table',
            'viz_type' => $vizType,
            'adhoc_filters' => [],
            'time_range' => 'No filter',
            'row_limit' => 10000,
        ];

        return match ($type) {
            'big_number' => [...$base, 'metric' => $metric, 'compare_lag' => 0, 'show_trend_line' => false],
            'bar', 'line' => [...$base, 'x_axis' => $dimension ?: $metricColumn, 'metrics' => [$metric], 'groupby' => [], 'order_desc' => true, 'show_legend' => true],
            'pie' => [...$base, 'groupby' => [$dimension ?: $metricColumn], 'metric' => $metric, 'show_legend' => true, 'show_labels' => true],
            default => [...$base, 'groupby' => array_values(array_filter([$dimension])), 'metrics' => [$metric], 'include_search' => true, 'page_length' => 25],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $charts
     * @return array<string, mixed>
     */
    private function positions(array $charts): array
    {
        $positions = [
            'DASHBOARD_VERSION_KEY' => 'v2',
            'ROOT_ID' => ['id' => 'ROOT_ID', 'type' => 'ROOT', 'children' => ['GRID_ID']],
            'GRID_ID' => ['id' => 'GRID_ID', 'type' => 'GRID', 'parents' => ['ROOT_ID'], 'children' => []],
        ];

        foreach ($charts as $index => $chart) {
            $chartId = (int) $chart['superset_chart_id'];
            $rowKey = 'ROW-'.$index;
            $chartKey = 'CHART-'.$chartId;
            $positions['GRID_ID']['children'][] = $rowKey;
            $positions[$rowKey] = [
                'id' => $rowKey,
                'type' => 'ROW',
                'parents' => ['ROOT_ID', 'GRID_ID'],
                'children' => [$chartKey],
                'meta' => ['background' => 'BACKGROUND_TRANSPARENT'],
            ];
            $positions[$chartKey] = [
                'id' => $chartKey,
                'type' => 'CHART',
                'parents' => ['ROOT_ID', 'GRID_ID', $rowKey],
                'children' => [],
                'meta' => [
                    'chartId' => $chartId,
                    'height' => 50,
                    'width' => 12,
                    'sliceName' => (string) $chart['title'],
                ],
            ];
        }

        return $positions;
    }

    private function habilitarEmbebido(int $dashboardId, ?string $existingUuid): string
    {
        $domains = config('superset.embed_allowed_domains', []);

        if (! is_array($domains) || $domains === []) {
            throw new RuntimeException('Defina SUPERSET_EMBED_ALLOWED_DOMAINS para habilitar la previsualización segura.');
        }

        $method = filled($existingUuid) ? 'put' : 'post';
        $response = $this->superset->request($method, '/api/v1/dashboard/'.$dashboardId.'/embedded', [
            'allowed_domains' => array_values($domains),
        ]);
        $uuid = $response['result']['uuid'] ?? $response['uuid'] ?? $existingUuid;

        if (! is_string($uuid) || $uuid === '') {
            throw new RuntimeException('Superset no devolvió el UUID embebido del tablero.');
        }

        return $uuid;
    }

    /** @param array<string, mixed> $response */
    private function idFrom(array $response, string $resource): int
    {
        $id = $response['id'] ?? $response['result']['id'] ?? null;

        if (! is_numeric($id)) {
            throw new RuntimeException('Superset no devolvió el identificador del '.$resource.'.');
        }

        return (int) $id;
    }
}
