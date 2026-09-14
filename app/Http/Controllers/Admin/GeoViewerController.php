<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeoViewerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeoViewerRequest;
use App\Http\Requests\UpdateGeoViewerRequest;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GeoViewerController extends Controller
{
    public function index(): View
    {
        return view('admin.geo-viewers.index', [
            'viewers' => GeoViewer::query()
                ->with(['approver', 'layers' => fn ($query) => $query->orderBy('geo_viewer_layers.sort_order')])
                ->orderBy('name')
                ->get(),
            'layers' => GeoLayer::query()->orderBy('group_name')->orderBy('name')->get(),
            'statuses' => GeoViewerStatus::cases(),
        ]);
    }

    public function store(StoreGeoViewerRequest $request, AuditLogger $audit): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['published_at'] = null;
        $geoViewer = GeoViewer::create($attributes);
        $audit->log(null, 'geo_viewer_created', $request->user(), ['geo_viewer_id' => $geoViewer->id], 'geovisors', [], $geoViewer->toArray());

        return back()->with('status', 'Visor creado. Ya puede asignarle capas y previsualizarlo.');
    }

    public function update(UpdateGeoViewerRequest $request, GeoViewer $geoViewer, AuditLogger $audit): RedirectResponse
    {
        $old = [
            'viewer' => $geoViewer->toArray(),
            'layers' => $geoViewer->layers()->get()->mapWithKeys(fn ($layer): array => [$layer->id => $layer->pivot->toArray()])->all(),
        ];
        $attributes = $request->safe()->only([
            'name', 'slug', 'description', 'center_latitude', 'center_longitude', 'initial_zoom', 'status',
        ]);

        $layerAssignments = collect($request->validated('layers', []))
            ->filter(fn (array $layer): bool => (bool) ($layer['included'] ?? false))
            ->mapWithKeys(fn (array $layer): array => [$layer['geo_layer_id'] => [
                'label' => $layer['label'] ?: null,
                'group_name' => $layer['group_name'] ?: null,
                'sort_order' => $layer['sort_order'],
                'visible_by_default' => (bool) ($layer['visible_by_default'] ?? false),
                'show_in_legend' => (bool) ($layer['show_in_legend'] ?? false),
                'opacity' => $layer['opacity'],
            ]])->all();

        DB::transaction(function () use ($geoViewer, $attributes, $layerAssignments): void {
            $geoViewer->update($attributes);
            $geoViewer->layers()->sync($layerAssignments);
        });

        $freshViewer = $geoViewer->fresh();
        $audit->log(null, 'geo_viewer_updated', $request->user(), ['geo_viewer_id' => $geoViewer->id], 'geovisors', $old, [
            'viewer' => $freshViewer->toArray(),
            'layers' => $freshViewer->layers()->get()->mapWithKeys(fn ($layer): array => [$layer->id => $layer->pivot->toArray()])->all(),
        ]);

        return back()->with('status', 'Configuración del visor guardada.');
    }
}
