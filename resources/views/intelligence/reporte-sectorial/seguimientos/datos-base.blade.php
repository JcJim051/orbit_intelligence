@extends('layouts.app', ['title' => 'Datos base · '.$seguimiento->etiqueta().' · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual de metas</p>
            <h1 class="page-title">Datos base · {{ $seguimiento->etiqueta() }}</h1>
            <p class="page-subtitle max-w-3xl">
                Matriz técnica de BPIN, techos, ejecución, saldo, líneas de pasiva, evidencias y regionalización.
                Corte al {{ $seguimiento->fecha_corte->format('d/m/Y') }}.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver al seguimiento</a>
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.pasiva-lineas.index', $seguimiento) }}">Líneas de pasiva</a>
            @can('cerrar', $seguimiento)
                <form method="post" action="{{ route('intelligence.reporte-mensual.cerrar', $seguimiento) }}" onsubmit="return confirm('Al cerrar el seguimiento su información queda congelada. ¿Continuar?')">
                    @csrf
                    <button class="btn-danger">Cerrar seguimiento</button>
                </form>
            @endcan
        </div>
    </header>

    @can('cargarPasiva', $seguimiento)
        <section class="panel">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Pasiva del PCT</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if($seguimiento->pasivaVigente)
                            Vigente: {{ $seguimiento->pasivaVigente->nombre_original }} ({{ $seguimiento->pasivaVigente->lineas_total }} líneas, {{ $seguimiento->pasivaVigente->lineas_pendientes }} pendientes) · cargada por {{ $seguimiento->pasivaVigente->subidoPor?->name }} el {{ $seguimiento->pasivaVigente->created_at->format('d/m/Y H:i') }} · base del techo: {{ str_replace('_', ' ', $seguimiento->pasivaVigente->base_techo) }}.
                        @else
                            Aún no hay pasiva. Adjunte el informe de ejecución presupuestal de gastos (InfMesPptoCDP) en .xlsx o .csv.
                        @endif
                        Una nueva carga reemplaza a la anterior y recalcula los techos (queda en el historial).
                    </p>
                </div>
                <form method="post" action="{{ route('intelligence.reporte-mensual.pasivas.store', $seguimiento) }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    <label class="field sm:min-w-64"><span class="sr-only">Archivo de pasiva</span><input type="file" name="archivo" accept=".xlsx,.csv" required></label>
                    <button class="btn-primary">Cargar pasiva</button>
                </form>
            </div>
            @if($lineasPendientes > 0)
                <p class="mt-3 text-sm text-amber-700">Hay {{ $lineasPendientes }} línea(s) de inversión sin BPIN, dependencia o fuente catalogada. <a class="font-semibold underline" href="{{ route('intelligence.reporte-mensual.pasiva-lineas.index', [$seguimiento, 'estado' => 'pendiente']) }}">Revisarlas</a>.</p>
            @endif
        </section>
    @endcan

    <section class="panel">
        <form method="get" action="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}" class="grid gap-4 md:grid-cols-4">
            @if($dependencias->count() > 1)
                <label class="field">
                    <span>Dependencia</span>
                    <select name="dependencia">
                        <option value="">Todas</option>
                        @foreach($dependencias as $dependencia)
                            <option value="{{ $dependencia->id }}" @selected($filtros['dependencia'] === $dependencia->id)>{{ $dependencia->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="field md:col-span-2">
                <span>Buscar BPIN o nombre</span>
                <input type="search" name="q" value="{{ $filtros['q'] }}">
            </label>
            <div class="flex items-end gap-2">
                <button class="btn-primary">Filtrar</button>
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Proyectos (BPIN)</h2>
            <p class="text-sm text-slate-500">{{ count($filas) }} en total</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3" rowspan="2">Proyecto</th>
                        <th class="px-4 py-3" rowspan="2">Dependencia</th>
                        @foreach($grupos as $grupo)
                            <th class="border-l border-slate-200 px-4 py-2 text-center" colspan="3">{{ $grupo->label() }}</th>
                        @endforeach
                        <th class="border-l border-slate-200 px-4 py-3" rowspan="2">Estado</th>
                        <th class="px-4 py-3" rowspan="2">Líneas pasiva</th>
                        <th class="px-4 py-3" rowspan="2">Sin evidencia</th>
                        <th class="px-4 py-3" rowspan="2">Regionalización</th>
                    </tr>
                    <tr>
                        @foreach($grupos as $grupo)
                            <th class="border-l border-slate-200 px-3 py-2 text-right">Techo</th>
                            <th class="px-3 py-2 text-right">Reportado</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filas as $indice => $fila)
                        <tr data-bpin="{{ $fila['proyecto']->bpin }}">
                            <td class="max-w-md px-4 py-3 align-top">
                                <a class="font-semibold text-indigo-700" href="{{ route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $fila['proyecto'], 'dependencia' => $fila['dependencia']->id]) }}">{{ $fila['proyecto']->etiqueta() }}</a>
                                @if($fila['proyecto']->tipo_focalizacion)
                                    <p class="mt-1 text-xs text-slate-500">{{ $fila['proyecto']->tipo_focalizacion->label() }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">{{ $fila['dependencia']->etiqueta() }}</td>
                            @foreach($grupos as $grupo)
                                @php($valores = $fila['grupos'][$grupo->value] ?? ['techo' => 0, 'reportado' => 0, 'saldo' => 0, 'fuentes' => []])
                                <td class="whitespace-nowrap border-l border-slate-100 px-3 py-3 text-right align-top" title="{{ collect($valores['fuentes'])->map(fn ($f) => $f['etiqueta'].': '.$pesos($f['techo']))->implode("\n") }}">{{ $pesos($valores['techo']) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right align-top">{{ $pesos($valores['reportado']) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right align-top {{ $valores['saldo'] < 0 ? 'text-red-700' : '' }}">{{ $pesos($valores['saldo']) }}</td>
                            @endforeach
                            <td class="border-l border-slate-100 px-4 py-3 align-top"><span class="status {{ $fila['estado']->cssClass() }}">{{ $fila['estado']->label() }}</span></td>
                            <td class="px-4 py-3 align-top">
                                <x-reporte-sectorial.conteo :conteo="$fila['lineas']->count()" :url="route('intelligence.reporte-mensual.proyectos.detalle', [$seguimiento, $fila['proyecto'], 'dependencia' => $fila['dependencia']->id, 'relacion' => 'lineas'])" titulo="Ver líneas de pasiva" />
                            </td>
                            <td class="px-4 py-3 align-top">
                                <x-reporte-sectorial.conteo :conteo="$fila['actividades_sin_evidencia']->count()" :url="route('intelligence.reporte-mensual.proyectos.detalle', [$seguimiento, $fila['proyecto'], 'dependencia' => $fila['dependencia']->id, 'relacion' => 'sin_evidencia'])" titulo="Ver avances sin evidencia" />
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if(! $fila['requiere_focalizacion'])
                                    <span class="text-slate-400">No aplica</span>
                                @elseif($fila['focalizacion_pendiente'])
                                    <span class="status status-error">Pendiente</span>
                                @else
                                    <span class="status status-approved">Completa</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 6 + count($grupos) * 3 }}" class="px-4 py-8 text-center text-slate-500">No hay proyectos con techo para su dependencia en este seguimiento.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@include('intelligence.catalogs.partials.count-detail-dialog', ['catalog' => new class { public function title(): string { return 'Reporte mensual de metas'; } }])
@endsection
