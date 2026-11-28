<?php

namespace App\Http\Controllers\Intelligence;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Services\Intelligence\IndicadoresTerritoriales\ConfiguracionReporteIndicadores;
use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorHistoricoIndicador;
use App\Services\Intelligence\IndicadoresTerritoriales\PlantillaHistoricoIndicador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndicadorTerritorialHistoricoController extends Controller
{
    public function edit(Request $request, IndicadorTerritorial $indicador): View
    {
        $this->authorizeEdit($request, $indicador);
        $indicador->load(['datos.reportadoPor:id,name', 'datos.validadoPor:id,name']);

        return view('intelligence.indicadores-territoriales.edit-serie', [
            'indicador' => $indicador,
            'canDeleteApproved' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        $data = $request->validate([
            'periodo' => ['required', 'string', 'max:100'],
            'valor' => ['required', 'numeric'],
            'periodo_inicio' => ['nullable', 'date_format:Y-m-d'],
            'periodo_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:periodo_inicio'],
            'fecha_corte' => ['nullable', 'date_format:Y-m-d'],
            'fuente' => ['nullable', 'string', 'max:2000'],
            'enlace_evidencia' => ['nullable', 'url', 'max:2048'],
            'observacion' => ['nullable', 'string', 'max:4000'],
        ]);

        $periodo = trim($data['periodo']);
        if ($indicador->datos()->whereRaw('lower(periodo) = ?', [mb_strtolower($periodo)])->exists()) {
            throw ValidationException::withMessages([
                'periodo' => 'Este indicador ya tiene un registro para el periodo indicado.',
            ]);
        }

        DB::transaction(function () use ($request, $indicador, $data, $periodo): void {
            $indicador->datos()->create([
                'periodo' => $periodo,
                'valor' => $data['valor'],
                'periodo_inicio' => $data['periodo_inicio'] ?? null,
                'periodo_fin' => $data['periodo_fin'] ?? null,
                'fecha_corte' => $data['fecha_corte'] ?? null,
                'fuente' => filled($data['fuente'] ?? null) ? trim($data['fuente']) : null,
                'enlace_evidencia' => filled($data['enlace_evidencia'] ?? null) ? trim($data['enlace_evidencia']) : null,
                'observacion' => filled($data['observacion'] ?? null) ? trim($data['observacion']) : null,
                'estado' => IndicadorTerritorialDato::ESTADO_PENDIENTE,
                'origen' => 'edicion_manual',
                'reportado_por' => $request->user()->id,
                'reportado_at' => now(),
            ]);

            $indicador->update(['estado' => 'historico_cargado']);
            $indicador->actualizarLineaBaseHistorica();
        });

        return redirect()
            ->to(Workspace::getUrl(['workspace' => 'indicador-territorial-serie-editar', 'record' => $indicador->getRouteKey()]))
            ->with('status', 'Nuevo registro creado. Quedó pendiente de validación.');
    }

    public function update(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        $data = $request->validate([
            'datos' => ['required', 'array'],
            'datos.*.periodo' => ['required', 'string', 'max:100'],
            'datos.*.valor' => ['required', 'numeric'],
            'datos.*.periodo_inicio' => ['nullable', 'date_format:Y-m-d'],
            'datos.*.periodo_fin' => ['nullable', 'date_format:Y-m-d'],
            'datos.*.fecha_corte' => ['nullable', 'date_format:Y-m-d'],
            'datos.*.fuente' => ['nullable', 'string'],
            'datos.*.enlace_evidencia' => ['nullable', 'url', 'max:2048'],
            'datos.*.observacion' => ['nullable', 'string'],
            'justificacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $records = $indicador->datos()->whereKey(array_keys($data['datos']))->get()->keyBy('id');
        if ($records->count() !== count($data['datos'])) {
            throw ValidationException::withMessages(['datos' => 'La serie contiene registros que no pertenecen a este indicador.']);
        }

        $changes = [];
        foreach ($data['datos'] as $id => $values) {
            $record = $records->get((int) $id);
            $periodo = trim($values['periodo']);
            $periodoDuplicado = $indicador->datos()
                ->where('id', '!=', $record->id)
                ->where('territorio_codigo', $record->territorio_codigo)
                ->where('desagregacion_tipo', $record->desagregacion_tipo)
                ->where('desagregacion_valor', $record->desagregacion_valor)
                ->whereRaw('lower(periodo) = ?', [mb_strtolower($periodo)])
                ->exists();
            if ($periodoDuplicado) {
                throw ValidationException::withMessages([
                    "datos.{$id}.periodo" => "Ya existe otro registro para el periodo {$periodo} con el mismo territorio y desagregación.",
                ]);
            }
            $normalized = [
                'periodo' => $periodo,
                'valor' => $values['valor'],
                'periodo_inicio' => $values['periodo_inicio'] ?? null,
                'periodo_fin' => $values['periodo_fin'] ?? null,
                'fecha_corte' => $values['fecha_corte'] ?? null,
                'fuente' => filled($values['fuente'] ?? null) ? trim($values['fuente']) : null,
                'enlace_evidencia' => filled($values['enlace_evidencia'] ?? null) ? trim($values['enlace_evidencia']) : null,
                'observacion' => filled($values['observacion'] ?? null) ? trim($values['observacion']) : null,
            ];
            if ($normalized['periodo_inicio'] && $normalized['periodo_fin'] && $normalized['periodo_fin'] < $normalized['periodo_inicio']) {
                throw ValidationException::withMessages([
                    "datos.{$id}.periodo_fin" => "La fecha final del periodo {$record->periodo} no puede ser anterior a su fecha inicial.",
                ]);
            }
            $record->fill($normalized);
            if ($record->isDirty()) {
                $changes[] = [$record, $normalized, $record->getOriginal()];
            }
        }

        $approvedChanged = collect($changes)->contains(fn (array $change): bool => $change[0]->estado === IndicadorTerritorialDato::ESTADO_APROBADO);
        if ($approvedChanged && ! $request->user()->isAdmin()) {
            throw ValidationException::withMessages([
                'datos' => 'Los datos aprobados solamente pueden ser corregidos por un administrador técnico.',
            ]);
        }
        if ($approvedChanged && blank($data['justificacion'] ?? null)) {
            throw ValidationException::withMessages([
                'justificacion' => 'Explique por qué se modifica uno o más datos que ya estaban aprobados.',
            ]);
        }

        if ($changes === []) {
            return $this->toIndicator($indicador)->with('status', 'La serie no tenía cambios por guardar.');
        }

        DB::transaction(function () use ($changes, $data, $request, $indicador): void {
            foreach ($changes as [$record, $normalized, $original]) {
                DB::table('indicador_territorial_dato_ediciones')->insert([
                    'indicador_territorial_dato_id' => $record->id,
                    'editado_por' => $request->user()->id,
                    'datos_anteriores' => json_encode(collect($original)->only(array_keys($normalized))->all(), JSON_UNESCAPED_UNICODE),
                    'datos_nuevos' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
                    'justificacion' => $data['justificacion'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $record->forceFill([
                    ...$normalized,
                    'estado' => IndicadorTerritorialDato::ESTADO_PENDIENTE,
                    'origen' => 'edicion_manual',
                    'reportado_por' => $request->user()->id,
                    'validado_por' => null,
                    'validado_at' => null,
                ])->save();
            }

            $indicador->update(['estado' => 'historico_cargado']);
            $indicador->actualizarLineaBaseHistorica();
        });

        return $this->toIndicator($indicador)->with('status', count($changes).' registro(s) actualizados. Los cambios quedaron pendientes de validación.');
    }

    public function template(Request $request, IndicadorTerritorial $indicador, PlantillaHistoricoIndicador $template): BinaryFileResponse
    {
        $this->authorizeIndicator($request, $indicador);

        $directory = storage_path('app/private/templates');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/historico-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $indicador->codigo).'-'.uniqid().'.xlsx';
        $template->write($indicador, $path);

        return response()->download($path, 'plantilla-historico-'.$indicador->codigo.'.xlsx')->deleteFileAfterSend(true);
    }

    public function import(Request $request, IndicadorTerritorial $indicador, ImportadorHistoricoIndicador $importer): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);
        abort_unless(! in_array($indicador->estado, ['precargado', 'revision_tecnica'], true), 422, 'Primero debe aprobar la ficha técnica del indicador.');

        $data = $request->validate([
            'archivo_historico' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'justificacion_correccion' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $data['archivo_historico'];
        try {
            $result = $importer->import(
                $indicador,
                $file->getRealPath(),
                $request->user(),
                $file->getClientOriginalName(),
                $data['justificacion_correccion'] ?? null,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['archivo_historico' => $exception->getMessage()]);
        }

        return $this->toIndicator($indicador)->with(
            'status',
            "Serie procesada: {$result['created']} nuevo(s), {$result['updated']} actualizado(s) y {$result['unchanged']} sin cambios. Los registros modificados quedaron pendientes de validación."
        );
    }

    public function destroy(Request $request, IndicadorTerritorial $indicador, IndicadorTerritorialDato $dato): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);
        abort_unless((int) $dato->indicador_territorial_id === (int) $indicador->id, 404);
        abort_if($dato->estado === IndicadorTerritorialDato::ESTADO_APROBADO && ! $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'justificacion_eliminacion' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $indicador, $dato, $data): void {
            DB::table('indicador_territorial_dato_eliminaciones')->insert([
                'indicador_territorial_id' => $indicador->id,
                'indicador_territorial_dato_id' => $dato->id,
                'eliminado_por' => $request->user()->id,
                'datos_anteriores' => json_encode($dato->getAttributes(), JSON_UNESCAPED_UNICODE),
                'justificacion' => trim($data['justificacion_eliminacion']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $dato->delete();
            $indicador->actualizarLineaBaseHistorica();

            $estado = $indicador->datos()->where('estado', IndicadorTerritorialDato::ESTADO_PENDIENTE)->exists()
                ? 'historico_cargado'
                : ($indicador->datos()->where('estado', IndicadorTerritorialDato::ESTADO_APROBADO)->exists()
                    ? 'historico_validado'
                    : 'pendiente_historico');
            $indicador->update(['estado' => $estado]);
        });

        return redirect()
            ->to(Workspace::getUrl(['workspace' => 'indicador-territorial-serie-editar', 'record' => $indicador->getRouteKey()]))
            ->with('status', "El periodo {$dato->periodo} fue eliminado y quedó registrado en la auditoría.");
    }

    public function validateAll(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless(
            $request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador),
            403
        );

        $pending = $indicador->datos()->where('estado', 'pendiente')->where('origen', '!=', 'reporte_mensual')->count();
        if ($pending === 0) {
            return $this->toIndicator($indicador)->withErrors(['historico' => 'No hay registros históricos pendientes de validación.']);
        }

        DB::transaction(function () use ($request, $indicador): void {
            $indicador->datos()->where('estado', 'pendiente')->where('origen', '!=', 'reporte_mensual')->update([
                'estado' => IndicadorTerritorialDato::ESTADO_APROBADO,
                'validado_por' => $request->user()->id,
                'validado_at' => now(),
                'updated_at' => now(),
            ]);
            $indicador->update(['estado' => 'historico_validado']);
            $indicador->actualizarLineaBaseHistorica();
        });

        return $this->toIndicator($indicador)->with('status', $pending.' registro(s) históricos aprobados. La línea base fue recalculada con el periodo más antiguo.');
    }

    private function authorizeIndicator(Request $request, IndicadorTerritorial $indicador): void
    {
        abort_unless($request->user()?->canAccessTerritorialIndicators(), 403);
        abort_unless($request->user()->canCoordinateTerritorialIndicators() || (int) $indicador->asignado_a === (int) $request->user()->id, 403);
    }

    private function authorizeEdit(Request $request, IndicadorTerritorial $indicador): void
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless($request->user()->canEditTerritorialIndicator($indicador), 403);
        abort_if(
            $request->user()->isInformationReviewer()
                && ! app(ConfiguracionReporteIndicadores::class)->edicionValidadoresActiva(),
            403
        );
    }

    private function toIndicator(IndicadorTerritorial $indicador): RedirectResponse
    {
        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->getRouteKey()]));
    }
}
