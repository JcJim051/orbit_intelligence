<?php

namespace App\Http\Controllers\Intelligence;

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorHistoricoIndicador;
use App\Services\Intelligence\IndicadoresTerritoriales\PlantillaHistoricoIndicador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndicadorTerritorialHistoricoController extends Controller
{
    public function edit(Request $request, IndicadorTerritorial $indicador): View
    {
        $this->authorizeEdit($request, $indicador);
        $indicador->load(['datos.reportadoPor:id,name', 'datos.validadoPor:id,name']);

        return view('intelligence.indicadores-territoriales.edit-serie', [
            'indicador' => $indicador,
        ]);
    }

    public function update(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeEdit($request, $indicador);

        $data = $request->validate([
            'datos' => ['required', 'array'],
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
            $normalized = [
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
        ]);

        $file = $data['archivo_historico'];
        $result = $importer->import($indicador, $file->getRealPath(), $request->user(), $file->getClientOriginalName());

        return $this->toIndicator($indicador)->with(
            'status',
            "Serie histórica cargada: {$result['created']} periodo(s) nuevo(s) y {$result['updated']} actualizado(s). Quedaron pendientes de validación."
        );
    }

    public function validateAll(Request $request, IndicadorTerritorial $indicador): RedirectResponse
    {
        $this->authorizeIndicator($request, $indicador);
        abort_unless(
            $request->user()->canCoordinateTerritorialIndicators() || $request->user()->canReviewTerritorialIndicator($indicador),
            403
        );

        $pending = $indicador->datos()->where('estado', 'pendiente')->count();
        if ($pending === 0) {
            return $this->toIndicator($indicador)->withErrors(['historico' => 'No hay registros históricos pendientes de validación.']);
        }

        DB::transaction(function () use ($request, $indicador): void {
            $indicador->datos()->where('estado', 'pendiente')->update([
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
    }

    private function toIndicator(IndicadorTerritorial $indicador): RedirectResponse
    {
        return redirect()->to(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicador->getRouteKey()]));
    }
}
