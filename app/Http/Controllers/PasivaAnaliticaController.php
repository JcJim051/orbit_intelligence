<?php

namespace App\Http\Controllers;

use App\Models\PasivaRubro;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PasivaAnaliticaController extends Controller
{
    public function store(Request $request, ImportadorPasivaAnalitica $importador): mixed
    {
        $this->authorize('import', PasivaRubro::class);

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,csv,txt'],
            'corte' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $archivo = $request->file('archivo');

        try {
            $resultado = $importador->importar(
                $archivo->getRealPath(),
                strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension()),
                $datos['corte'] ?? null,
            );
        } catch (InvalidArgumentException|ValidationException $exception) {
            $mensaje = $exception instanceof ValidationException
                ? (collect($exception->errors())->flatten()->first() ?: 'No se pudo leer la pasiva.')
                : $exception->getMessage();

            return back()->withErrors(['archivo' => $mensaje]);
        }

        $mensaje = $resultado->estado === 'omitida'
            ? 'Ese archivo ya estaba cargado para el corte '.$resultado->corte.' ('.$resultado->rubros.' rubros). No se duplicó.'
            : 'Se cargaron '.$resultado->rubros.' rubros del corte '.$resultado->corte.'. El tablero muestra el corte más reciente.';

        return back()->with('status', $mensaje);
    }
}
