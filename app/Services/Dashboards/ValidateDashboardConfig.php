<?php

namespace App\Services\Dashboards;

use App\Models\GeoViewer;
use App\Models\TabularDataSource;

class ValidateDashboardConfig
{
    /** @param array<string, mixed> $config
     * @return array<int, string>
     */
    public function errors(array $config): array
    {
        $errors = [];
        $widgets = collect($config['widgets'] ?? []);
        if ($widgets->isEmpty()) {
            return ['Agregue por lo menos un componente.'];
        }
        $dataWidgets = $widgets->whereNotIn('type', ['map', 'text']);
        $source = ! empty($config['data_source_id']) ? TabularDataSource::query()->with('currentVersion')->find($config['data_source_id']) : null;
        if ($dataWidgets->isNotEmpty() && $source?->currentVersion === null) {
            $errors[] = 'Seleccione una fuente tabular para los indicadores y gráficas.';
        }
        $fieldKeys = collect($source?->currentVersion?->fields ?? [])->pluck('key');
        foreach ($dataWidgets as $widget) {
            foreach (array_filter([$widget['query']['field'] ?? null, $widget['query']['category'] ?? null, $widget['query']['series'] ?? null]) as $field) {
                if (! $fieldKeys->contains($field)) {
                    $errors[] = "El componente «{$widget['title']}» usa el campo inexistente «{$field}».";
                }
            }
        }
        if ($widgets->contains('type', 'map')) {
            $viewer = ! empty($config['map']['geo_viewer_id']) ? GeoViewer::find($config['map']['geo_viewer_id']) : null;
            if ($viewer === null) {
                $errors[] = 'Seleccione un geovisor para el componente de mapa.';
            } elseif (! $viewer->isPublished()) {
                $errors[] = 'El geovisor seleccionado todavía no está publicado. Publíquelo antes de enviar el dashboard a revisión.';
            }
            if (blank($config['map']['join_layer_field'] ?? null) || blank($config['map']['join_data_field'] ?? null)) {
                $errors[] = 'Defina los campos que relacionan mapa y datos.';
            }
        }

        return array_values(array_unique($errors));
    }
}
