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
        abort_unless($geoViewer->isPublished(), 404);

        return response()->json($config->handle($geoViewer))
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
