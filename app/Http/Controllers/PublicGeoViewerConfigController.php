<?php

namespace App\Http\Controllers;

use App\Models\GeoViewer;
use App\Services\Geovisors\BuildGeoViewerConfig;
use Illuminate\Http\JsonResponse;

class PublicGeoViewerConfigController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GeoViewer $geoViewer, BuildGeoViewerConfig $config): JsonResponse
    {
        $geoViewer->loadMissing('publication');
        abort_unless($geoViewer->isPubliclyVisible() || auth()->user()?->canAccessSpatialGovernance(), 404);

        return response()->json($config->handle($geoViewer))
            ->header('Cache-Control', 'no-store');
    }
}
