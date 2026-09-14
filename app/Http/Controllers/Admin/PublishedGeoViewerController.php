<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Http\Controllers\Controller;
use App\Models\GeoViewer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublishedGeoViewerController extends Controller
{
    public function __invoke(Request $request, GeoViewer $geoViewer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve-spatial-publication');
        abort_if($geoViewer->isPublished(), 409, 'Este geovisor ya está publicado.');

        if (! $geoViewer->layers()->where('geo_layers.active', true)->exists()) {
            return back()->with('error', 'El geovisor debe tener por lo menos una capa disponible antes de publicarse.');
        }

        $unclassified = $geoViewer->layers()
            ->where('geo_layers.active', true)
            ->where('geo_layers.access_policy', GeoLayerAccessPolicy::Pending->value)
            ->pluck('geo_layers.name');
        if ($unclassified->isNotEmpty()) {
            return back()->with('error', 'Antes de publicar, defina si estas capas permiten descarga: '.$unclassified->join(', ').'.');
        }

        $old = $geoViewer->toArray();
        $geoViewer->update([
            'status' => GeoViewerStatus::Published,
            'published_at' => now(),
            'approved_by' => $request->user()->id,
        ]);
        $audit->log(null, 'geo_viewer_published', $request->user(), [
            'geo_viewer_id' => $geoViewer->id,
        ], 'geovisors', $old, $geoViewer->fresh()->toArray());

        return back()->with('status', 'Publicación aprobada. El geovisor ya está disponible en el portal oficial.');
    }
}
