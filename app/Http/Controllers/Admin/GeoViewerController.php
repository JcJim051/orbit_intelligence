<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeoViewerRequest;
use App\Http\Requests\UpdateGeoViewerRequest;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\SpatialDataset;
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
            'managedDatasets' => SpatialDataset::query()
                ->whereNotNull('physical_table')
                ->with(['versions' => fn ($query) => $query
                    ->where('status', DatasetFormVersionStatus::Published->value)
                    ->with('fields')
                    ->orderByDesc('version')])
                ->get()
                ->keyBy('slug'),
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
                'label' => $layer['label'] ?? null,
                'group_name' => $layer['group_name'] ?? null,
                'sort_order' => $layer['sort_order'],
                'visible_by_default' => (bool) ($layer['visible_by_default'] ?? false),
                'show_in_legend' => (bool) ($layer['show_in_legend'] ?? false),
                'opacity' => $layer['opacity'],
            ]])->all();

        if ($geoViewer->isPublished()) {
            if (! GeoLayer::query()->whereIn('id', array_keys($layerAssignments))->where('active', true)->exists()) {
                return back()->with('error', 'El visor público debe conservar al menos una capa disponible.');
            }
            $newLayerIds = array_diff(array_keys($layerAssignments), $geoViewer->layers()->pluck('geo_layers.id')->all());
            $availableNewLayers = GeoLayer::query()
                ->whereIn('id', $newLayerIds)
                ->where('active', true)
                ->where('access_policy', '!=', GeoLayerAccessPolicy::Pending->value)
                ->count();
            if ($availableNewLayers !== count($newLayerIds)) {
                return back()->with('error', 'Antes de incluir una capa nueva en un visor público, actívela y defina su acceso para la comunidad.');
            }
        }

        DB::transaction(function () use ($geoViewer, $attributes, $layerAssignments, $request): void {
            if ($geoViewer->isPublished()) {
                $attributes['approved_by'] = $request->user()->id;
                $attributes['published_at'] = now();
            }
            $geoViewer->update($attributes);
            $geoViewer->layers()->sync($layerAssignments);
        });

        $freshViewer = $geoViewer->fresh();
        $audit->log(null, 'geo_viewer_updated', $request->user(), ['geo_viewer_id' => $geoViewer->id], 'geovisors', $old, [
            'viewer' => $freshViewer->toArray(),
            'layers' => $freshViewer->layers()->get()->mapWithKeys(fn ($layer): array => [$layer->id => $layer->pivot->toArray()])->all(),
        ]);

        return back()->with('status', $geoViewer->isPublished()
            ? 'Cambios aprobados y aplicados al visor público sin cambiar su dirección.'
            : 'Configuración del visor guardada.');
    }
}
