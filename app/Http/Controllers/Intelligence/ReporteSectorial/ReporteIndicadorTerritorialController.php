<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\Seguimiento;
use App\Services\Intelligence\IndicadoresTerritoriales\CronogramaReporteIndicadores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteIndicadorTerritorialController extends Controller
{
    public function store(
        Request $request,
        Seguimiento $seguimiento,
        Dependencia $dependencia,
        IndicadorTerritorial $indicador,
        CronogramaReporteIndicadores $cronograma,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user?->perteneceADependencia($dependencia->id) || $user?->gestionaReporteSectorial(),
            403
        );
        abort_if($seguimiento->estaCerrado(), 422, 'El corte está cerrado y no admite nuevos reportes.');
        abort_unless((int) $indicador->dependencia_id === (int) $dependencia->id, 404);
        abort_unless(in_array($indicador->estado, ['historico_validado', 'listo_habilitar', 'activo'], true), 422, 'El indicador aún no está habilitado para seguimiento periódico.');

        $periodo = $cronograma->periodoExigible($indicador->periodicidad, $seguimiento);
        if ($periodo === null) {
            throw ValidationException::withMessages(['indicador' => 'Este indicador no debe reportarse en el corte seleccionado.']);
        }

        if ($indicador->datos()->where('periodo', $periodo['periodo'])->exists()) {
            throw ValidationException::withMessages(['indicador' => 'El periodo ya fue reportado. El sector no puede modificar un dato entregado.']);
        }

        $data = $request->validate([
            'valor' => ['required', 'numeric'],
            'enlace_evidencia' => ['nullable', 'url', 'max:2048'],
            'observacion' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($data, $indicador, $seguimiento, $periodo, $user): void {
            IndicadorTerritorialDato::query()->create([
                'indicador_territorial_id' => $indicador->id,
                'seguimiento_id' => $seguimiento->id,
                'periodo' => $periodo['periodo'],
                'periodo_inicio' => $periodo['periodo_inicio'],
                'periodo_fin' => $periodo['periodo_fin'],
                'valor' => $data['valor'],
                'fecha_corte' => $seguimiento->fecha_corte,
                'fuente' => $indicador->fuente_informacion,
                'enlace_evidencia' => $data['enlace_evidencia'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'estado' => IndicadorTerritorialDato::ESTADO_PENDIENTE,
                'origen' => 'reporte_mensual',
                'reportado_por' => $user->id,
                'reportado_at' => now(),
            ]);

            if ($indicador->estado !== 'activo') {
                $indicador->update(['estado' => 'activo']);
            }
        });

        return $this->volver($seguimiento, $dependencia)->with('status', 'Indicador reportado. El dato quedó pendiente de validación por el enlace de información.');
    }

    public function review(Request $request, IndicadorTerritorialDato $dato): RedirectResponse
    {
        $dato->load('indicador');
        abort_unless(
            $request->user()?->canCoordinateTerritorialIndicators() || $request->user()?->canReviewTerritorialIndicator($dato->indicador),
            403
        );
        abort_unless($dato->origen === 'reporte_mensual', 422, 'Esta acción corresponde únicamente a reportes periódicos.');

        $data = $request->validate([
            'decision' => ['required', 'in:aprobar,observar'],
            'observacion_revision' => ['nullable', 'required_if:decision,observar', 'string', 'max:4000'],
        ]);

        $dato->update([
            'estado' => $data['decision'] === 'aprobar' ? IndicadorTerritorialDato::ESTADO_APROBADO : IndicadorTerritorialDato::ESTADO_OBSERVADO,
            'observacion_revision' => $data['observacion_revision'] ?? null,
            'validado_por' => $request->user()->id,
            'validado_at' => now(),
        ]);
        $dato->indicador->actualizarLineaBaseHistorica();

        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $dato->indicador_territorial_id]))
            ->with('status', $data['decision'] === 'aprobar' ? 'Reporte periódico aprobado.' : 'Reporte periódico observado.');
    }

    public function approvePending(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        abort_unless(
            $request->user()?->canCoordinateTerritorialIndicators() || $request->user()?->canReviewTerritorialIndicator($indicador),
            403
        );

        $pendientes = $indicador->datos()
            ->where('origen', 'reporte_mensual')
            ->where('estado', IndicadorTerritorialDato::ESTADO_PENDIENTE)
            ->count();
        if ($pendientes === 0) {
            return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->id]))
                ->withErrors(['reportes' => 'No hay reportes periódicos pendientes de aprobación.']);
        }

        $indicador->datos()
            ->where('origen', 'reporte_mensual')
            ->where('estado', IndicadorTerritorialDato::ESTADO_PENDIENTE)
            ->update([
                'estado' => IndicadorTerritorialDato::ESTADO_APROBADO,
                'validado_por' => $request->user()->id,
                'validado_at' => now(),
                'updated_at' => now(),
            ]);
        $indicador->actualizarLineaBaseHistorica();

        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->id]))
            ->with('status', $pendientes.' reporte(s) periódico(s) aprobados.');
    }

    private function volver(Seguimiento $seguimiento, Dependencia $dependencia): RedirectResponse
    {
        return redirect()->to(Workspace::getUrl([
            'workspace' => 'seguimiento-dependencia-reportar',
            'record' => $dependencia->id,
            'seguimiento' => $seguimiento->id,
        ]));
    }
}
