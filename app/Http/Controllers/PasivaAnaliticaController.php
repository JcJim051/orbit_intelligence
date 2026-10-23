<?php

namespace App\Http\Controllers;

use App\Models\PasivaRubro;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use Illuminate\Http\Request;
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
            $total = $importador->importar(
                $archivo->getRealPath(),
                strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension()),
                $datos['corte'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['corte' => $exception->getMessage()]);
        }

        return back()->with('status', 'Se cargaron '.$total.' rubros en el esquema analitica.');
    }
}
