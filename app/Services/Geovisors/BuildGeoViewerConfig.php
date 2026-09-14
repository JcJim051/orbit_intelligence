<?php

namespace App\Services\Geovisors;

use App\Models\GeoViewer;

class BuildGeoViewerConfig
{
    /**
     * @return array<string, mixed>
     */
    public function handle(GeoViewer $geoViewer): array
    {
        $geoViewer->load(['layers' => fn ($query) => $query
            ->where('geo_layers.active', true)
            ->orderBy('geo_viewer_layers.sort_order')
            ->orderBy('geo_layers.name')]);

        return [
            'viewer' => [
                'name' => $geoViewer->name,
                'slug' => $geoViewer->slug,
                'description' => $geoViewer->description,
                'center' => [$geoViewer->center_latitude, $geoViewer->center_longitude],
                'zoom' => $geoViewer->initial_zoom,
                'published_at' => $geoViewer->published_at?->toIso8601String(),
            ],
            'layers' => $geoViewer->layers->map(fn ($layer): array => [
                'slug' => $layer->slug,
                'name' => $layer->pivot->label ?: $layer->name,
                'group' => $layer->pivot->group_name ?: ($layer->group_name ?: 'Otras capas'),
                'source' => [
                    'type' => $layer->source_type,
                    'url' => $layer->source_url,
                    'layer_name' => $layer->source_layer_name,
                ],
                'geometry_type' => $layer->geometry_type,
                'popup_fields' => $layer->popup_fields ?? [],
                'style' => $layer->style ?? [],
                'attribution' => $layer->attribution,
                'min_zoom' => $layer->min_zoom,
                'max_zoom' => $layer->max_zoom,
                'visible_by_default' => (bool) $layer->pivot->visible_by_default,
                'show_in_legend' => (bool) $layer->pivot->show_in_legend,
                'opacity' => (float) $layer->pivot->opacity,
                'access_policy' => $layer->access_policy->value,
                'download' => $layer->isDownloadable() ? [
                    'allowed' => true,
                    'url' => $layer->download_url ?: $layer->source_url,
                    'format' => $layer->download_format ?: 'geojson',
                ] : [
                    'allowed' => false,
                ],
            ])->values()->all(),
        ];
    }
}
