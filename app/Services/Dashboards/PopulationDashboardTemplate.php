<?php

namespace App\Services\Dashboards;

class PopulationDashboardTemplate
{
    /** @return array<string, mixed> */
    public function make(): array
    {
        return [
            'theme' => ['primary' => '#047857', 'female' => '#e89ca3', 'male' => '#1683c4'],
            'map' => ['geo_viewer_id' => null, 'join_layer_field' => 'codigo_dane', 'join_data_field' => 'codigo_dane'],
            'data_source_id' => null,
            'global_filters' => ['year' => null],
            'widgets' => [
                $this->widget('dept-total', 'indicator', 'Población departamental', 'departamental_fijo', 0, 0, 3, 3, ['operation' => 'sum', 'field' => 'poblacion_total']),
                $this->widget('dept-female', 'indicator', 'Población femenina departamental', 'departamental_fijo', 0, 3, 3, 3, ['operation' => 'sum', 'field' => 'poblacion_femenina']),
                $this->widget('dept-male', 'indicator', 'Población masculina departamental', 'departamental_fijo', 0, 6, 3, 3, ['operation' => 'sum', 'field' => 'poblacion_masculina']),
                $this->widget('municipal-total', 'indicator', 'Población total', 'seleccion_territorial', 3, 0, 2, 1, ['operation' => 'sum', 'field' => 'poblacion_total']),
                $this->widget('municipal-female', 'indicator', 'Población femenina', 'seleccion_territorial', 3, 1, 2, 1, ['operation' => 'sum', 'field' => 'poblacion_femenina']),
                $this->widget('municipal-male', 'indicator', 'Población masculina', 'seleccion_territorial', 3, 2, 2, 1, ['operation' => 'sum', 'field' => 'poblacion_masculina']),
                $this->widget('rural-urban', 'donut', 'Distribución rural/urbana', 'seleccion_territorial', 3, 3, 2, 3, ['category' => 'zona', 'operation' => 'sum', 'field' => 'poblacion']),
                $this->widget('population-pyramid', 'pyramid', 'Pirámide poblacional', 'seleccion_territorial', 3, 6, 2, 3, ['category' => 'grupo_edad', 'series' => 'sexo', 'operation' => 'sum', 'field' => 'poblacion']),
                $this->widget('territory-map', 'map', 'Municipios del Meta', 'global', 5, 0, 7, 9, []),
            ],
        ];
    }

    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function widget(string $id, string $type, string $title, string $scope, int $x, int $y, int $w, int $h, array $query): array
    {
        return compact('id', 'type', 'title', 'scope', 'x', 'y', 'w', 'h', 'query');
    }
}
