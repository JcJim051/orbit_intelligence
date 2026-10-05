<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\SeguimientoCargaHistorica;
use App\Services\Intelligence\ReporteSectorial\AnaliticaMetasProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnaliticaSeguimientoController extends Controller
{
    public function index(Request $request, Seguimiento $seguimiento, AnaliticaMetasProducto $analitica): View
    {
        $this->authorize('view', $seguimiento);

        $filtros = $this->filtros($request);
        $usuario = $request->user();
        $dependenciaIdsVisibles = $this->dependenciaIdsVisibles($usuario);
        $filtros['dependencia'] = $this->dependenciaFiltrada($filtros['dependencia'], $dependenciaIdsVisibles);
        $datos = $analitica->construir($seguimiento, $filtros, $dependenciaIdsVisibles);

        return view('intelligence.reporte-sectorial.seguimientos.analitica', [
            'seguimiento' => $seguimiento,
            'filas' => $datos['filas'],
            'resumen' => $datos['resumen'],
            'filtros' => $filtros,
            'dependencias' => $this->dependenciasDisponibles($dependenciaIdsVisibles),
            'estados' => $this->estados(),
            'ultimaCargaHistorica' => $this->ultimaCargaHistorica($seguimiento),
            'reportesConsolidados' => $this->conteoReportes($seguimiento, $dependenciaIdsVisibles),
        ]);
    }

    public function download(Request $request, Seguimiento $seguimiento, AnaliticaMetasProducto $analitica): StreamedResponse
    {
        $this->authorize('view', $seguimiento);

        $filtros = $this->filtros($request);
        $dependenciaIdsVisibles = $this->dependenciaIdsVisibles($request->user());
        $filtros['dependencia'] = $this->dependenciaFiltrada($filtros['dependencia'], $dependenciaIdsVisibles);
        $datos = $analitica->construir($seguimiento, $filtros, $dependenciaIdsVisibles);
        $filename = 'analitica-metas-producto-'.$seguimiento->vigencia.'-'.$seguimiento->mes.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($datos): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'pilar_codigo',
                'pilar_nombre',
                'programa_codigo',
                'programa_nombre',
                'dependencia_codigo',
                'dependencia_nombre',
                'meta_producto_codigo',
                'meta_producto_nombre',
                'bpines',
                'proyectos',
                'fuentes',
                'programacion_fisica',
                'avance_fisico',
                'porcentaje_fisico',
                'asignado',
                'comprometido',
                'obligado',
                'pagado',
                'porcentaje_financiero',
                'estado_analitico',
                'observaciones',
            ], ';');

            /** @var Collection<int, array<string, mixed>> $filas */
            $filas = $datos['filas'];

            foreach ($filas as $fila) {
                fputcsv($handle, [
                    $this->csvValue($fila['pilar_codigo']),
                    $this->csvValue($fila['pilar_nombre']),
                    $this->csvValue($fila['programa_codigo']),
                    $this->csvValue($fila['programa_nombre']),
                    $this->csvValue($fila['dependencia_codigo']),
                    $this->csvValue($fila['dependencia_nombre']),
                    $this->csvValue($fila['meta_codigo']),
                    $this->csvValue($fila['meta_nombre']),
                    $this->csvValue(collect($fila['proyectos'])->pluck('bpin')->implode(' | ')),
                    $this->csvValue(collect($fila['proyectos'])->pluck('nombre')->implode(' | ')),
                    $this->csvValue(implode(' | ', $fila['fuentes'])),
                    round((float) $fila['programacion_fisica'], 4),
                    round((float) $fila['avance_fisico'], 4),
                    round((float) $fila['porcentaje_fisico'], 2),
                    round((float) $fila['asignado'], 2),
                    round((float) $fila['comprometido'], 2),
                    round((float) $fila['obligado'], 2),
                    round((float) $fila['pagado'], 2),
                    round((float) $fila['porcentaje_financiero'], 2),
                    $this->estados()[$fila['estado_analitico']] ?? $fila['estado_analitico'],
                    $this->csvValue(implode(' | ', $fila['observaciones'])),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{dependencia: int|null, q: string, estado: string}
     */
    private function filtros(Request $request): array
    {
        return [
            'dependencia' => $request->integer('dependencia') ?: null,
            'q' => trim((string) $request->query('q', '')),
            'estado' => (string) $request->query('estado', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function estados(): array
    {
        return [
            'sin_programacion' => 'Sin programación',
            'sin_avance' => 'Sin avance',
            'en_ejecucion' => 'En ejecución',
            'cumplida' => 'Cumplida',
            'sobrecumplida' => 'Sobrecumplida',
        ];
    }

    private function csvValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return Str::startsWith($value, ['=', '+', '-', '@']) ? "'{$value}" : $value;
    }

    /**
     * @return list<int>|null
     */
    private function dependenciaIdsVisibles(mixed $usuario): ?array
    {
        if ($usuario?->veTodosLosSectores()) {
            return null;
        }

        return $usuario?->dependenciaIdsAsignadas() ?? [];
    }

    private function dependenciaFiltrada(?int $dependenciaId, ?array $dependenciaIdsVisibles): ?int
    {
        if ($dependenciaId === null || $dependenciaIdsVisibles === null) {
            return $dependenciaId;
        }

        return in_array($dependenciaId, $dependenciaIdsVisibles, true) ? $dependenciaId : -1;
    }

    private function dependenciasDisponibles(?array $dependenciaIdsVisibles): Collection
    {
        return Dependencia::query()
            ->when(is_array($dependenciaIdsVisibles), fn ($query) => $query->whereIn('id', $dependenciaIdsVisibles ?: [0]))
            ->orderBy('nombre')
            ->get();
    }

    private function ultimaCargaHistorica(Seguimiento $seguimiento): ?SeguimientoCargaHistorica
    {
        return SeguimientoCargaHistorica::query()
            ->where('seguimiento_id', $seguimiento->id)
            ->latest('id')
            ->first();
    }

    private function conteoReportes(Seguimiento $seguimiento, ?array $dependenciaIdsVisibles): int
    {
        return ReporteProyecto::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->when(is_array($dependenciaIdsVisibles), fn ($query) => $query->whereIn('dependencia_id', $dependenciaIdsVisibles ?: [0]))
            ->count();
    }
}
