<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Enums\EstadoRevisionPasiva;
use App\Http\Controllers\Controller;
use App\Models\PasivaLinea;
use App\Models\Seguimiento;
use App\Services\Intelligence\ReporteSectorial\ImportadorPasiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PasivaController extends Controller
{
    public function store(Request $request, Seguimiento $seguimiento, ImportadorPasiva $importador): RedirectResponse
    {
        $this->authorize('cargarPasiva', $seguimiento);

        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:51200'],
        ], [
            'archivo.extensions' => 'Guarde la pasiva del PCT como .xlsx o .csv antes de cargarla.',
        ]);

        $carga = $importador->importar($seguimiento, $request->file('archivo'), $request->user());

        return redirect()->route('intelligence.reporte-mensual.show', $seguimiento)->with('status', sprintf(
            'Pasiva cargada: %d líneas (%d de inversión, %d pendientes de revisión). Techos recalculados.',
            $carga->lineas_total,
            $carga->lineas_inversion,
            $carga->lineas_pendientes,
        ));
    }

    /**
     * Líneas de la pasiva vigente. El sector solo ve las asignadas a su dependencia (scope global), en solo lectura.
     */
    public function index(Request $request, Seguimiento $seguimiento): View
    {
        $this->authorize('view', $seguimiento);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoRevisionPasiva::class)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $lineas = PasivaLinea::query()
            ->where('seguimiento_id', $seguimiento->id)
            ->vigentes()
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado_revision', $estado))
            ->when($filtros['q'] ?? null, fn ($query, $texto) => $query->where(fn ($buscar) => $buscar->where('bpin', 'like', "%{$texto}%")->orWhere('identificacion_presupuestal', 'like', "%{$texto}%")->orWhere('nombre_proyecto', 'like', "%{$texto}%")))
            ->with(['fuente', 'dependencia', 'proyecto'])
            ->orderBy('fila')
            ->get();

        return view('intelligence.reporte-sectorial.pasivas.index', [
            'seguimiento' => $seguimiento,
            'lineas' => $lineas,
            'filtros' => ['estado' => $filtros['estado'] ?? '', 'q' => $filtros['q'] ?? ''],
        ]);
    }
}
