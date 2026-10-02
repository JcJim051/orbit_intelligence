<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Techo;
use App\Services\Intelligence\ReporteSectorial\CalculadoraTechos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechoController extends Controller
{
    /**
     * Ajuste manual excepcional (solo Gerencia), con motivo y soporte obligatorios. Queda en techo_historial.
     */
    public function ajustar(Request $request, Techo $techo, CalculadoraTechos $calculadora): RedirectResponse
    {
        $this->authorize('ajustar', $techo);

        $datos = $request->validate([
            'valor' => ['required', 'numeric', 'min:0'],
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
            'soporte' => ['required', 'file', 'max:20480'],
        ]);

        $calculadora->ajustar($techo, (float) $datos['valor'], $datos['motivo'], $request->file('soporte'), $request->user());

        return back()->with('status', 'Techo ajustado y registrado en el historial.');
    }
}
