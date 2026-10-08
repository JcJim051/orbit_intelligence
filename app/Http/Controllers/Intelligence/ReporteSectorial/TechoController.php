<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Proyecto;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Services\Intelligence\ReporteSectorial\CalculadoraTechos;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechoController extends Controller
{
    public function __construct(private readonly ServicioReporteSectorial $servicio) {}

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

    /**
     * Agrega una fuente y sus cuatro valores cuando el análisis de la pasiva quedó incompleto.
     */
    public function store(Request $request, Seguimiento $seguimiento, Proyecto $proyecto, CalculadoraTechos $calculadora): RedirectResponse
    {
        $dependencia = $this->dependencia($request, $seguimiento, $proyecto);
        $this->authorize('crearFuente', [Techo::class, $seguimiento, $dependencia]);

        $calculadora->corregir($seguimiento, $proyecto, $dependencia, null, $this->datosFuente($request), $request->user());

        return back()->with('status', 'Fuente de financiación registrada en el techo.');
    }

    /**
     * Corrige la fuente o cualquiera de sus cuatro valores. La pasiva original se conserva como referencia.
     */
    public function update(Request $request, Techo $techo, CalculadoraTechos $calculadora): RedirectResponse
    {
        $this->authorize('corregirFuente', $techo);

        $calculadora->corregir($techo->seguimiento, $techo->proyecto, $techo->dependencia, $techo, $this->datosFuente($request), $request->user());

        return back()->with('status', 'Fuente de financiación corregida.');
    }

    public function destroy(Request $request, Techo $techo, CalculadoraTechos $calculadora): RedirectResponse
    {
        $this->authorize('corregirFuente', $techo);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], $this->mensajesMotivo());

        $calculadora->eliminar($techo, $datos['motivo'], $request->user());

        return back()->with('status', 'Fuente de financiación retirada del techo.');
    }

    /**
     * @return array{fuente_financiacion_id: int, asignado: float, comprometido: float, obligado: float, pagado: float, motivo: string}
     */
    private function datosFuente(Request $request): array
    {
        $datos = $request->validate([
            'fuente_financiacion_id' => ['required', 'integer', 'exists:fuentes_financiacion,id'],
            'asignado' => ['required', 'numeric', 'min:0'],
            'comprometido' => ['required', 'numeric', 'min:0'],
            'obligado' => ['required', 'numeric', 'min:0'],
            'pagado' => ['required', 'numeric', 'min:0'],
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'fuente_financiacion_id.required' => 'Seleccione la fuente de financiación.',
            'fuente_financiacion_id.exists' => 'La fuente de financiación no existe.',
            'asignado.required' => 'Indique el valor asignado.',
            'comprometido.required' => 'Indique el valor comprometido.',
            'obligado.required' => 'Indique el valor obligado.',
            'pagado.required' => 'Indique el valor pagado.',
        ] + $this->mensajesMotivo());

        return [
            'fuente_financiacion_id' => (int) $datos['fuente_financiacion_id'],
            'asignado' => (float) $datos['asignado'],
            'comprometido' => (float) $datos['comprometido'],
            'obligado' => (float) $datos['obligado'],
            'pagado' => (float) $datos['pagado'],
            'motivo' => $datos['motivo'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajesMotivo(): array
    {
        return [
            'motivo.required' => 'Indique el motivo de la corrección.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }

    private function dependencia(Request $request, Seguimiento $seguimiento, Proyecto $proyecto): Dependencia
    {
        return $this->servicio->dependenciaDelReporte($seguimiento, $proyecto, $request->integer('dependencia') ?: null);
    }
}
