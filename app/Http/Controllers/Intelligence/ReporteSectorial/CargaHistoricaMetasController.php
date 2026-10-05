<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Seguimiento;
use App\Models\SeguimientoCargaHistorica;
use App\Services\Intelligence\ReporteSectorial\CargaHistoricaMetas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CargaHistoricaMetasController extends Controller
{
    public function index(Request $request, Seguimiento $seguimiento): View
    {
        $this->authorize('importarHistorico', $seguimiento);

        $cargas = SeguimientoCargaHistorica::query()
            ->where('seguimiento_id', $seguimiento->id)
            ->with('usuario')
            ->latest()
            ->get();

        $seleccionada = $request->integer('carga')
            ? $cargas->firstWhere('id', $request->integer('carga'))
            : $cargas->first();

        return view('intelligence.reporte-sectorial.seguimientos.importar-historico', [
            'seguimiento' => $seguimiento,
            'cargas' => $cargas,
            'carga' => $seleccionada,
        ]);
    }

    public function diagnosticar(Request $request, Seguimiento $seguimiento, CargaHistoricaMetas $servicio): RedirectResponse
    {
        $this->authorize('importarHistorico', $seguimiento);

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:51200'],
            'archivo_proyectos' => ['nullable', 'file', 'extensions:xlsx,csv,txt', 'max:51200'],
        ], [
            'archivo.extensions' => 'Cargue la matriz histórica como .xlsx, .csv o .txt.',
            'archivo_proyectos.extensions' => 'Cargue la matriz de proyectos como .xlsx, .csv o .txt.',
        ]);

        $archivo = $datos['archivo'];
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
        $path = $archivo->store('reporte-sectorial/historicas/'.$seguimiento->id, 'local');
        $ruta = Storage::disk('local')->path($path);
        $archivoProyectos = $datos['archivo_proyectos'] ?? null;
        $pathProyectos = null;
        $rutaProyectos = null;
        $extensionProyectos = null;

        if ($archivoProyectos) {
            $extensionProyectos = strtolower($archivoProyectos->getClientOriginalExtension() ?: 'xlsx');
            $pathProyectos = $archivoProyectos->store('reporte-sectorial/historicas/'.$seguimiento->id, 'local');
            $rutaProyectos = Storage::disk('local')->path($pathProyectos);
        }

        $diagnostico = $servicio->diagnosticar($seguimiento, $ruta, $extension, $rutaProyectos, $extensionProyectos);

        $carga = SeguimientoCargaHistorica::query()->create([
            'seguimiento_id' => $seguimiento->id,
            'disk' => 'local',
            'path' => $path,
            'nombre_original' => $archivo->getClientOriginalName(),
            'sha256' => hash_file('sha256', $ruta),
            'proyectos_path' => $pathProyectos,
            'proyectos_nombre_original' => $archivoProyectos?->getClientOriginalName(),
            'proyectos_sha256' => $rutaProyectos ? hash_file('sha256', $rutaProyectos) : null,
            'uploaded_by' => $request->user()->id,
            'status' => 'diagnosticado',
            'filas_total' => $diagnostico['summary']['total'],
            'filas_validas' => $diagnostico['summary']['validas'],
            'filas_bloqueadas' => $diagnostico['summary']['bloqueadas'],
            'diagnostico' => $diagnostico,
        ]);

        return redirect()
            ->route('intelligence.reporte-mensual.historicas.index', [$seguimiento, 'carga' => $carga->id])
            ->with('status', sprintf(
                'Diagnóstico listo: %d filas válidas y %d bloqueadas.',
                $carga->filas_validas,
                $carga->filas_bloqueadas,
            ));
    }

    public function importar(Seguimiento $seguimiento, SeguimientoCargaHistorica $carga, CargaHistoricaMetas $servicio): RedirectResponse
    {
        $this->authorize('importarHistorico', $seguimiento);
        abort_unless($carga->seguimiento_id === $seguimiento->id, 404);

        $resultado = $servicio->importar($carga, request()->user());

        return redirect()
            ->route('intelligence.reporte-mensual.historicas.index', [$seguimiento, 'carga' => $carga->id])
            ->with('status', sprintf(
                'Carga histórica importada: %d filas nuevas y %d actualizadas. Las filas bloqueadas quedaron en diagnóstico.',
                $resultado['importadas'],
                $resultado['actualizadas'],
            ));
    }

    public function errores(Seguimiento $seguimiento, SeguimientoCargaHistorica $carga): StreamedResponse
    {
        $this->authorize('importarHistorico', $seguimiento);
        abort_unless($carga->seguimiento_id === $seguimiento->id, 404);

        $nombre = 'errores-carga-historica-'.$seguimiento->vigencia.'-'.$seguimiento->mes.'-'.$carga->id.'.csv';
        $filas = collect($carga->diagnostico['filas'] ?? [])->where('estado', 'bloqueada')->values();

        return Response::streamDownload(function () use ($filas): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['fila', 'codigo_meta_producto', 'meta_producto', 'responsable', 'errores', 'notas']);

            foreach ($filas as $fila) {
                fputcsv($out, [
                    $fila['fila'] ?? '',
                    $fila['codigo_meta_producto'] ?? '',
                    $fila['meta_producto'] ?? '',
                    $fila['responsable'] ?? '',
                    implode(' | ', $fila['errores'] ?? []),
                    implode(' | ', $fila['notas'] ?? []),
                ]);
            }

            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
