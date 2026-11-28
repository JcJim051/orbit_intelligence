<?php

namespace App\Http\Controllers\InteligenciaGeografica;

use App\Http\Controllers\Controller;
use App\Models\InteligenciaGeografica\Capa;
use App\Services\InteligenciaGeografica\GeoJsonCapa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapaGeoJsonController extends Controller
{
    public function __invoke(Request $request, string $capa, GeoJsonCapa $geoJson): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', Capa::class), 403);

        $modelo = Capa::query()->findOrFail($capa);

        return response()->json($geoJson->porBbox($modelo, (string) $request->query('bbox', '')));
    }
}
