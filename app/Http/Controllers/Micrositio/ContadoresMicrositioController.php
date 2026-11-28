<?php

namespace App\Http\Controllers\Micrositio;

use App\Http\Controllers\Controller;
use App\Models\MicrositioCifra;
use App\Services\Micrositio\ContadoresMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContadoresMicrositioController extends Controller
{
    public function detalle(Request $request, string $origen, ContadoresMicrositio $contadores, PublicadorMicrositio $publicador): JsonResponse
    {
        abort_unless(in_array($origen, ContadoresMicrositio::ORIGENES, true), 404);

        $preview = $request->boolean('preview') && ($request->user()?->can('update', MicrositioCifra::class) ?? false);
        $registros = $preview ? $publicador->registrosBorrador('cifras') : $publicador->registrosPublicados('cifras');
        $criterio = 'activos';

        foreach ($registros as $registro) {
            if (($registro['atributos']['origen'] ?? null) === $origen) {
                $criterio = (string) ($registro['atributos']['criterio'] ?: 'activos');
                break;
            }
        }

        return response()->json([
            'origen' => $origen,
            'criterio' => $criterio,
            'total' => $contadores->valor($origen, $criterio),
            'grupos' => $contadores->desglose($origen, $criterio),
        ]);
    }
}
