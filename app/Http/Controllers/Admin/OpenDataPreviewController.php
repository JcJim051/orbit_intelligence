<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenDataSource;
use App\Services\OpenData\BuildOpenDataGeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenDataPreviewController extends Controller
{
    public function __invoke(Request $request, OpenDataSource $source, BuildOpenDataGeoJson $builder): JsonResponse
    {
        abort_unless($request->user()->canAccessSpatialGovernance(), 403);

        $force = $request->query('force') !== null && $request->query('force') !== '0';

        return response()->json($builder->handle($source, $request->except(['force', 'revision']), $force));
    }
}
