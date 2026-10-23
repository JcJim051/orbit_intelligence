<?php

namespace App\Http\Controllers;

use App\Services\Micrositio\HojaMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MicrositioExcelController extends Controller
{
    public function __construct(
        private readonly HojaMicrositio $hoja,
        private readonly PublicadorMicrositio $publicador,
    ) {}

    public function exportar(string $seccion): BinaryFileResponse
    {
        $this->authorize('export', $this->publicador->modelo($seccion));
        $ruta = storage_path('app/private/micrositio-'.$seccion.'-'.now()->format('YmdHis').'.xlsx');
        $this->hoja->exportar($seccion, $ruta);

        return response()->download($ruta, 'micrositio-'.$seccion.'.xlsx')->deleteFileAfterSend();
    }

    public function plantilla(string $seccion): BinaryFileResponse
    {
        $this->authorize('export', $this->publicador->modelo($seccion));
        $ruta = storage_path('app/private/plantilla-micrositio-'.$seccion.'.xlsx');
        $this->hoja->plantilla($seccion, $ruta);

        return response()->download($ruta, 'plantilla-micrositio-'.$seccion.'.xlsx')->deleteFileAfterSend();
    }

    public function importar(Request $request, string $seccion): mixed
    {
        $this->authorize('import', $this->publicador->modelo($seccion));
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx'],
        ]);

        try {
            $total = $this->hoja->importar($seccion, $request->file('archivo')->getRealPath());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Se importaron '.$total.' filas al borrador. Revise la vista previa y publique.');
    }
}
