<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaAgriculturalMapRequest;
use App\Services\Geovisors\BuildEvaAgriculturalMap;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicEvaAgriculturalMapController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EvaAgriculturalMapRequest $request, BuildEvaAgriculturalMap $map): JsonResponse
    {
        try {
            return response()
                ->json($map->handle(
                    $request->integer('year', (int) config('eva.default_year')),
                    $request->string('crop', (string) config('eva.default_crop'))->toString(),
                    $request->string('metric', (string) config('eva.default_metric'))->toString(),
                ))
                ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=21600');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No fue posible construir la capa EVA con los límites municipales del Meta.',
            ], 503);
        }
    }
}
