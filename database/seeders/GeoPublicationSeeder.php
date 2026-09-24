<?php

namespace Database\Seeders;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GeoPublicationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $municipalities = $this->layer('limites-municipales-meta', [
            'name' => 'Límites municipales del Meta',
            'group_name' => 'División política',
            'source_url' => '/api/public/geodata/limites-municipales-meta',
            'geometry_type' => 'polygon',
            'popup_fields' => ['mpio_cnmbr'],
            'style' => ['color' => '#ffffff', 'fillColor' => '#4f46e5', 'weight' => 2, 'radius' => 7],
            'attribution' => 'Límites municipales oficiales MGN 2024 · DANE',
        ]);

        $waterways = $this->layer('corredores-hidricos-demo', [
            'name' => 'Corredores hídricos demostrativos',
            'group_name' => 'Hídrico ambiental',
            'source_url' => '/data/geovisores/corredores-hidricos-demo.geojson',
            'geometry_type' => 'line',
            'popup_fields' => ['nombre', 'categoria', 'estado_monitoreo', 'municipios'],
            'style' => ['color' => '#0284c7', 'fillColor' => '#38bdf8', 'weight' => 4, 'radius' => 7],
            'attribution' => 'Datos demostrativos · no constituyen cartografía oficial',
        ]);

        $criticalPoints = $this->layer('puntos-criticos-demo', [
            'name' => 'Puntos críticos demostrativos',
            'group_name' => 'Gestión del riesgo',
            'source_url' => '/data/geovisores/puntos-criticos-demo.geojson',
            'geometry_type' => 'point',
            'popup_fields' => [
                'codigo', 'municipio', 'evento', 'nivel_riesgo', 'estado', 'horas_maquina',
                'poblacion_afectada', 'cultivos_afectados_ha', 'animales_afectados',
            ],
            'style' => ['color' => '#991b1b', 'fillColor' => '#ef4444', 'weight' => 2, 'radius' => 9],
            'attribution' => 'Datos demostrativos · no constituyen un reporte oficial',
        ]);

        $demographics = $this->layer('indicadores-municipales-demo', [
            'name' => 'Indicadores municipales demostrativos',
            'group_name' => 'Sociodemografía',
            'source_url' => '/data/geovisores/indicadores-municipales-demo.geojson',
            'geometry_type' => 'point',
            'popup_fields' => [
                'municipio', 'poblacion_demo', 'mujeres_porcentaje', 'hombres_porcentaje',
                'urbana_porcentaje', 'rural_porcentaje',
            ],
            'style' => ['color' => '#5b21b6', 'fillColor' => '#8b5cf6', 'weight' => 2, 'radius' => 10],
            'attribution' => 'Cifras demostrativas · reemplazar por fuente estadística oficial',
        ]);

        $territorial = $this->viewer('territorial-meta', [
            'name' => 'Territorio y ambiente del Meta',
            'description' => 'Ejemplo de división política, fuentes hídricas y contexto ambiental.',
        ]);
        $territorial->layers()->sync([
            $municipalities->id => $this->assignment('División política', 10, true, 0.65),
            $waterways->id => $this->assignment('Hídrico ambiental', 20, true),
        ]);

        $risk = $this->viewer('gestion-riesgo-meta', [
            'name' => 'Gestión del riesgo del Meta',
            'description' => 'Ejemplo de puntos críticos, afectaciones e intervenciones de mitigación.',
        ]);
        $risk->layers()->sync([
            $criticalPoints->id => $this->assignment('Puntos críticos', 10, true),
            $waterways->id => $this->assignment('Contexto hídrico', 20, false, 0.7),
        ]);

        $sociodemographic = $this->viewer('sociodemografico-meta', [
            'name' => 'Panorama sociodemográfico del Meta',
            'description' => 'Ejemplo territorial para consultar indicadores de población por municipio.',
        ]);
        $sociodemographic->layers()->sync([
            $municipalities->id => $this->assignment('División política', 10, true, 0.35),
            $demographics->id => $this->assignment('Indicadores sociodemográficos', 20, true),
        ]);

        $this->call(EvaGeoPublicationSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function layer(string $slug, array $attributes): GeoLayer
    {
        return GeoLayer::updateOrCreate(['slug' => $slug], [
            'source_type' => 'geojson',
            'min_zoom' => 6,
            'max_zoom' => 18,
            'active' => true,
            'access_policy' => GeoLayerAccessPolicy::Downloadable,
            'download_format' => 'geojson',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function viewer(string $slug, array $attributes): GeoViewer
    {
        return GeoViewer::updateOrCreate(['slug' => $slug], [
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => GeoViewerStatus::Draft,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    private function assignment(string $group, int $order, bool $visible, float $opacity = 1): array
    {
        return [
            'group_name' => $group,
            'sort_order' => $order,
            'visible_by_default' => $visible,
            'show_in_legend' => true,
            'opacity' => $opacity,
        ];
    }
}
