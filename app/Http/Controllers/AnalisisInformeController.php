<?php

namespace App\Http\Controllers;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use App\Services\InteligenciaGeografica\ExportarCruces;
use App\Services\InteligenciaGeografica\InformePdf;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AnalisisInformeController extends Controller
{
    public function pdf(AnalisisArea $analisis, InformePdf $informe)
    {
        $this->authorize('view', $analisis);

        return response($informe->pdf($analisis), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe-analisis-'.$analisis->id.'.pdf"',
        ]);
    }

    public function cruces(AnalisisArea $analisis, string $formato, ExportarCruces $cruces)
    {
        $this->authorize('view', $analisis);

        return $this->descarga($cruces, $analisis, null, $formato);
    }

    public function crucesCapa(AnalisisArea $analisis, Capa $capa, string $formato, ExportarCruces $cruces)
    {
        $this->authorize('view', $analisis);

        return $this->descarga($cruces, $analisis, $capa, $formato);
    }

    private function descarga(ExportarCruces $cruces, AnalisisArea $analisis, ?Capa $capa, string $formato): Response
    {
        try {
            $archivo = $cruces->archivo($analisis, $capa, $formato);
        } catch (RuntimeException $excepcion) {
            abort(503, $excepcion->getMessage());
        }

        if ($archivo === null) {
            abort(404);
        }

        return response($archivo['bytes'], 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$archivo['nombre'].'"',
        ]);
    }
}
