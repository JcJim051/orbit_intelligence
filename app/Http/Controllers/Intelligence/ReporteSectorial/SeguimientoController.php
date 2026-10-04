<?php

namespace App\Http\Controllers\Intelligence\ReporteSectorial;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\PasivaLinea;
use App\Models\Seguimiento;
use App\Services\Intelligence\ReporteSectorial\ResumenSectorial;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeguimientoController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', Seguimiento::class);

        $seguimientos = Seguimiento::query()->with(['pasivaVigente', 'creador'])->orderByDesc('vigencia')->orderByDesc('mes')->get();

        return view('intelligence.reporte-sectorial.seguimientos.index', [
            'seguimientos' => $seguimientos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Seguimiento::class);

        $datos = $request->validate([
            'vigencia' => ['required', 'integer', 'between:2024,2035'],
            'mes' => ['required', 'integer', 'between:1,12', Rule::unique('seguimientos')->where('vigencia', $request->integer('vigencia'))],
            'modo_captura' => ['nullable', Rule::in(array_keys(Seguimiento::modosCaptura()))],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'mes.unique' => 'Ya existe un seguimiento para ese mes y vigencia.',
        ]);

        if (Schema::hasColumn('seguimientos', 'modo_captura')) {
            $datos['modo_captura'] ??= Seguimiento::MODO_OPERATIVO;
        } else {
            unset($datos['modo_captura']);
        }

        $seguimiento = Seguimiento::query()->create($datos + ['created_by' => $request->user()->id]);

        return redirect()->route('intelligence.reporte-mensual.show', $seguimiento)->with('status', 'Seguimiento '.$seguimiento->etiqueta().' creado. Adjunte la pasiva del PCT para calcular los techos.');
    }

    public function show(Request $request, Seguimiento $seguimiento, ResumenSectorial $resumen): View
    {
        $this->authorize('view', $seguimiento);

        $dependenciaId = $request->integer('dependencia') ?: null;
        $buscar = $request->string('q')->trim()->toString() ?: null;
        $datos = $resumen->construir($seguimiento, $dependenciaId, $buscar);
        $filas = collect($datos['filas']);
        $usuario = $request->user();

        return view('intelligence.reporte-sectorial.seguimientos.show', [
            'seguimiento' => $seguimiento->load(['pasivaVigente.subidoPor', 'pasivaCargas', 'creador']),
            'dependencias' => $usuario->veTodosLosSectores()
                ? Dependencia::query()->orderBy('nombre')->get()
                : Dependencia::query()->whereIn('id', $usuario->dependenciaIdsAsignadas())->orderBy('nombre')->get(),
            'filtros' => ['dependencia' => $dependenciaId, 'q' => $request->string('q')->toString()],
            'filas' => $filas,
            'resumen' => [
                'proyectos' => $filas->count(),
                'reportados' => $filas->where('estado.value', 'reportado')->count(),
                'aprobados' => $filas->where('estado.value', 'aprobado')->count(),
                'devueltos' => $filas->where('estado.value', 'devuelto')->count(),
                'borradores' => $filas->where('estado.value', 'borrador')->count(),
                'pendientes_evidencia' => $filas->sum(fn (array $fila): int => $fila['actividades_sin_evidencia']->count()),
                'regionalizacion_pendiente' => $filas->where('focalizacion_pendiente', true)->count(),
                'techo' => $filas->sum(fn (array $fila): float => (float) $fila['total']['techo']),
                'reportado' => $filas->sum(fn (array $fila): float => (float) $fila['total']['reportado']),
                'saldo' => $filas->sum(fn (array $fila): float => (float) $fila['total']['saldo']),
            ],
            'lineasPendientes' => PasivaLinea::query()->where('seguimiento_id', $seguimiento->id)->vigentes()->pendientesRevision()->count(),
        ]);
    }

    public function edit(Seguimiento $seguimiento): View
    {
        $this->authorize('update', $seguimiento);

        return view('intelligence.reporte-sectorial.seguimientos.edit', [
            'seguimiento' => $seguimiento,
        ]);
    }

    public function update(Request $request, Seguimiento $seguimiento): RedirectResponse
    {
        $this->authorize('update', $seguimiento);

        $datos = $request->validate([
            'vigencia' => ['required', 'integer', 'between:2024,2035'],
            'mes' => [
                'required',
                'integer',
                'between:1,12',
                Rule::unique('seguimientos')
                    ->where('vigencia', $request->integer('vigencia'))
                    ->ignore($seguimiento->id),
            ],
            'modo_captura' => ['nullable', Rule::in(array_keys(Seguimiento::modosCaptura()))],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'mes.unique' => 'Ya existe un seguimiento para ese mes y vigencia.',
        ]);

        $datos['fecha_corte'] = Carbon::create((int) $datos['vigencia'], (int) $datos['mes'], 1)->endOfMonth();
        if (Schema::hasColumn('seguimientos', 'modo_captura')) {
            $datos['modo_captura'] ??= $seguimiento->modo_captura ?: Seguimiento::MODO_OPERATIVO;
        } else {
            unset($datos['modo_captura']);
        }

        $seguimiento->update($datos);

        return redirect()->route('intelligence.reporte-mensual.show', $seguimiento)->with('status', 'Seguimiento actualizado a '.$seguimiento->fresh()->etiqueta().'.');
    }

    public function datosBase(Request $request, Seguimiento $seguimiento, ResumenSectorial $resumen): View
    {
        $this->authorize('view', $seguimiento);

        $dependenciaId = $request->integer('dependencia') ?: null;
        $datos = $resumen->construir($seguimiento, $dependenciaId, $request->string('q')->trim()->toString() ?: null);
        $usuario = $request->user();

        return view('intelligence.reporte-sectorial.seguimientos.datos-base', [
            'seguimiento' => $seguimiento->load(['pasivaVigente.subidoPor', 'pasivaCargas']),
            'filas' => $datos['filas'],
            'grupos' => $datos['grupos'],
            'dependencias' => $usuario->veTodosLosSectores()
                ? Dependencia::query()->orderBy('nombre')->get()
                : Dependencia::query()->whereIn('id', $usuario->dependenciaIdsAsignadas())->orderBy('nombre')->get(),
            'filtros' => ['dependencia' => $dependenciaId, 'q' => $request->string('q')->toString()],
            'lineasPendientes' => PasivaLinea::query()->where('seguimiento_id', $seguimiento->id)->vigentes()->pendientesRevision()->count(),
        ]);
    }

    public function cerrar(Request $request, Seguimiento $seguimiento, ServicioReporteSectorial $servicio): RedirectResponse
    {
        $this->authorize('cerrar', $seguimiento);

        $servicio->cerrar($seguimiento, $request->user());

        return back()->with('status', 'Seguimiento '.$seguimiento->etiqueta().' cerrado. Su información quedó congelada.');
    }
}
