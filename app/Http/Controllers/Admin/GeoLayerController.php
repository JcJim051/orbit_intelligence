<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeoLayerAccessPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeoLayerRequest;
use App\Http\Requests\UpdateGeoLayerRequest;
use App\Models\GeoLayer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class GeoLayerController extends Controller
{
    public function store(StoreGeoLayerRequest $request, AuditLogger $audit): RedirectResponse
    {
        $geoLayer = GeoLayer::create($this->attributes($request));
        $audit->log(null, 'geo_layer_created', $request->user(), ['geo_layer_id' => $geoLayer->id], 'geovisors', [], $geoLayer->toArray());

        return back()->with('status', 'Capa registrada. Ahora puede asignarla a uno o varios visores.');
    }

    public function update(UpdateGeoLayerRequest $request, GeoLayer $geoLayer, AuditLogger $audit): RedirectResponse
    {
        $old = $geoLayer->toArray();
        $geoLayer->update($this->attributes($request));
        $audit->log(null, 'geo_layer_updated', $request->user(), ['geo_layer_id' => $geoLayer->id], 'geovisors', $old, $geoLayer->fresh()->toArray());

        return back()->with('status', 'Capa actualizada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreGeoLayerRequest|UpdateGeoLayerRequest $request): array
    {
        $validated = $request->safe()->only([
            'name', 'slug', 'group_name', 'source_type', 'source_url', 'source_layer_name', 'geometry_type', 'attribution', 'source_page_url', 'min_zoom', 'max_zoom',
            'access_policy', 'download_url', 'download_format', 'restriction_reason',
        ]);

        $policy = GeoLayerAccessPolicy::from($validated['access_policy']);

        return [
            ...$validated,
            'is_open_data' => $request->boolean('is_open_data'),
            'source_page_url' => $request->boolean('is_open_data') ? (($validated['source_page_url'] ?? null) ?: null) : null,
            'download_url' => ($validated['download_url'] ?? null) ?: null,
            'download_format' => $policy === GeoLayerAccessPolicy::Downloadable ? (($validated['download_format'] ?? null) ?: 'geojson') : null,
            'restriction_reason' => $policy === GeoLayerAccessPolicy::ViewOnly ? ($validated['restriction_reason'] ?? null) : null,
            'access_policy_approved_by' => $policy === GeoLayerAccessPolicy::Pending ? null : $request->user()->id,
            'access_policy_approved_at' => $policy === GeoLayerAccessPolicy::Pending ? null : now(),
            'popup_fields' => collect(preg_split('/[\s,]+/', $request->string('popup_fields')->toString()))
                ->map(fn (string $field): string => trim($field))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'style' => [
                'color' => $request->string('color')->toString(),
                'fillColor' => $request->string('fill_color')->toString(),
                'weight' => $request->integer('weight'),
                'radius' => $request->integer('radius'),
            ],
            'active' => $request->boolean('active'),
        ];
    }
}
