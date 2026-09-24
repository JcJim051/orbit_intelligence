<?php

namespace Database\Seeders;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EvaGeoPublicationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $layer = GeoLayer::updateOrCreate(['slug' => 'eva-agricola-meta'], [
            'name' => 'Producción agrícola EVA (Meta)',
            'group_name' => 'Actividad agropecuaria',
            'source_type' => 'geojson',
            'source_url' => '/api/public/geodata/eva-agricola-meta',
            'geometry_type' => 'polygon',
            'popup_fields' => [
                'municipio', 'cultivo', 'año', 'area_sembrada', 'area_cosechada',
                'produccion', 'rendimiento', 'metrica', 'valor', 'unidad',
            ],
            'style' => [
                'color' => '#ffffff',
                'fillColor' => '#15803d',
                'weight' => 1.5,
                'choropleth' => [
                    'property' => 'valor',
                    'palette' => ['#dcfce7', '#86efac', '#4ade80', '#16a34a', '#166534'],
                    'noDataColor' => '#e2e8f0',
                ],
            ],
            'filters' => $this->filters(),
            'attribution' => 'EVA 2019–2025 · UPRA · Datos Abiertos Colombia · límites MGN 2024 DANE',
            'is_open_data' => true,
            'source_page_url' => config('eva.source_url'),
            'min_zoom' => 6,
            'max_zoom' => 18,
            'active' => true,
            'access_policy' => GeoLayerAccessPolicy::Downloadable,
            'download_format' => 'geojson',
        ]);

        $viewer = GeoViewer::firstOrNew(['slug' => 'produccion-agricola-meta']);
        $viewer->fill([
            'name' => 'Producción agrícola del Meta',
            'description' => 'Coropleta municipal de área sembrada, producción y rendimiento reportados por EVA.',
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
        ]);
        if (! $viewer->exists) {
            $viewer->status = GeoViewerStatus::Draft;
        }
        $viewer->save();
        $viewer->layers()->sync([
            $layer->id => [
                'group_name' => 'Actividad agropecuaria',
                'sort_order' => 10,
                'visible_by_default' => true,
                'show_in_legend' => true,
                'opacity' => 1,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filters(): array
    {
        return [
            [
                'name' => 'year',
                'label' => 'Año',
                'default' => (string) config('eva.default_year'),
                'options' => collect(range(config('eva.maximum_year'), config('eva.minimum_year')))
                    ->map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year])
                    ->all(),
            ],
            [
                'name' => 'crop',
                'label' => 'Cultivo',
                'default' => config('eva.default_crop'),
                'options' => collect(config('eva.crops'))
                    ->map(fn (string $crop): array => ['value' => $crop, 'label' => $crop])
                    ->all(),
            ],
            [
                'name' => 'metric',
                'label' => 'Métrica',
                'default' => config('eva.default_metric'),
                'options' => collect(config('eva.metrics'))
                    ->map(fn (array $metric, string $key): array => ['value' => $key, 'label' => $metric['label']])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
