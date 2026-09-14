<?php

namespace App\Http\Controllers;

use App\Services\Geovisors\GetMetaMunicipalBoundaries;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicMetaMunicipalBoundariesController extends Controller
{
    public function __invoke(GetMetaMunicipalBoundaries $boundaries): JsonResponse
    {
        try {
            return response()
                ->json($boundaries->handle())
                ->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No fue posible consultar los límites municipales del Meta.'], 503);
        }
    }
}
