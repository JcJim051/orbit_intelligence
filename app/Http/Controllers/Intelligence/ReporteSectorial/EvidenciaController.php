<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Evidencia;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenciaController extends Controller
{
    public function show(Evidencia $evidencia): StreamedResponse
    {
        $this->authorize('view', $evidencia);

        return Storage::disk($evidencia->disk)->download($evidencia->path, $evidencia->nombre_original);
    }

    public function destroy(Evidencia $evidencia, ServicioReporteSectorial $servicio): RedirectResponse
    {
        $this->authorize('delete', $evidencia);

        $servicio->eliminarEvidencia($evidencia);

        return back()->with('status', 'Evidencia eliminada.');
    }
}
