<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoViewer;
use App\Services\Geovisors\BuildGeoViewerConfig;
use Illuminate\Http\JsonResponse;

class GeoViewerPreviewConfigController extends Controller
{
    public function __invoke(GeoViewer $geoViewer, BuildGeoViewerConfig $config): JsonResponse
    {
        return response()->json($config->handle($geoViewer))
            ->header('Cache-Control', 'no-store');
    }
}
