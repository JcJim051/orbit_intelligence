<?php

namespace App\Http\Controllers;

use App\Models\OpenDataSource;
use App\Services\OpenData\BuildOpenDataGeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class PublicOpenDataGeoJsonController extends Controller
{
    public function __invoke(Request $request, OpenDataSource $source, BuildOpenDataGeoJson $builder): JsonResponse
    {
        abort_unless($source->layer()->where('active', true)->whereHas('viewers', fn ($query) => $query->where('status', 'published'))->exists(), 404);
        try {
            return response()->json($builder->handle($source, $request->except('revision')))
                ->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=21600');
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'La fuente abierta no está disponible y todavía no existe una versión válida.'], 503);
        }
    }
}
