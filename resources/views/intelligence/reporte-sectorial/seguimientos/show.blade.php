@extends('layouts.app', ['title' => 'Seguimiento · '.$seguimiento->etiqueta().' · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual de metas</p>
            <h1 class="page-title">Seguimiento {{ $seguimiento->etiqueta() }}</h1>
            <p class="page-subtitle max-w-3xl">
                Corte al {{ $seguimiento->fecha_corte->format('d/m/Y') }} ·
                <span class="status {{ $seguimiento->estaCerrado() ? 'status-approved' : 'status-pending_review' }}">{{ $seguimiento->estado->label() }}</span>
                @if($seguimiento->estaCerrado()) · Información congelada; solo consulta. @endif
            </p>
            @if($seguimiento->observacion)
                <p class="mt-2 max-w-3xl text-sm text-slate-500">{{ $seguimiento->observacion }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.index') }}">Todos los seguimientos</a>
            @can('update', $seguimiento)
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.edit', $seguimiento) }}">Editar seguimiento</a>
            @endcan
            <a class="btn-primary" href="{{ route('intelligence.reporte-mensual.datos-base', $seguimiento) }}">Ver datos base</a>
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.pasiva-lineas.index', $seguimiento) }}">Líneas de pasiva</a>
            @can('cerrar', $seguimiento)
                <form method="post" action="{{ route('intelligence.reporte-mensual.cerrar', $seguimiento) }}" onsubmit="return confirm('Al cerrar el seguimiento su información queda congelada. ¿Continuar?')">
                    @csrf
                    <button class="btn-danger">Cerrar seguimiento</button>
                </form>
            @endcan
        </div>
    </header>

    <section class="panel">
        <form method="get" action="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'seguimiento', 'record' => $seguimiento->getRouteKey()]) }}" class="grid gap-4 md:grid-cols-5">
            @if($dependencias->count() > 1)
                <label class="field md:col-span-2">
                    <span>Alcance del resumen</span>
                    <select name="dependencia" onchange="this.form.submit()">
                        <option value="">Todas las dependencias visibles</option>
                        @foreach($dependencias as $dependencia)
                            <option value="{{ $dependencia->id }}" @selected($filtros['dependencia'] === $dependencia->id)>{{ $dependencia->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="field md:col-span-2">
                <span>Buscar proyecto</span>
                <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="BPIN o nombre del proyecto">
            </label>
            <div class="flex items-end gap-2">
                <button class="btn-primary">Actualizar</button>
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Proyectos con techo</p>
            <p class="mt-3 text-4xl font-black text-slate-950">{{ number_format($resumen['proyectos'], 0, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">BPIN × dependencia incluidos en el corte.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Techo total</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $pesos($resumen['techo']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Base calculada desde la pasiva vigente.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reportado</p>
            <p class="mt-3 text-3xl font-black text-slate-950">{{ $pesos($resumen['reportado']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Comprometido registrado por los sectores.</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo disponible</p>
            <p class="mt-3 text-3xl font-black {{ $resumen['saldo'] < 0 ? 'text-red-700' : 'text-slate-950' }}">{{ $pesos($resumen['saldo']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Diferencia entre techo y reportado.</p>
        </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="panel lg:col-span-2">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Estado del proceso de seguimiento</h2>
                    <p class="mt-1 text-sm text-slate-500">Resumen operativo para saber qué falta antes de cerrar el corte.</p>
                </div>
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.datos-base', $seguimiento) }}">Revisar matriz BPIN</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">En diligenciamiento</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($resumen['borradores'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reportados</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($resumen['reportados'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Devueltos</p>
                    <p class="mt-2 text-3xl font-black text-amber-700">{{ number_format($resumen['devueltos'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aprobados</p>
                    <p class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($resumen['aprobados'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <a class="rounded-2xl border {{ $lineasPendientes > 0 ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-700' }} p-4 hover:border-emerald-300" href="{{ route('intelligence.reporte-mensual.pasiva-lineas.index', [$seguimiento, 'estado' => $lineasPendientes > 0 ? 'pendiente' : null]) }}">
                    <p class="text-xs font-semibold uppercase tracking-wide">Líneas de pasiva pendientes</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($lineasPendientes, 0, ',', '.') }}</p>
                </a>
                <a class="rounded-2xl border {{ $resumen['pendientes_evidencia'] > 0 ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-700' }} p-4 hover:border-emerald-300" href="{{ route('intelligence.reporte-mensual.datos-base', $seguimiento) }}">
                    <p class="text-xs font-semibold uppercase tracking-wide">Avances sin evidencia</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($resumen['pendientes_evidencia'], 0, ',', '.') }}</p>
                </a>
                <a class="rounded-2xl border {{ $resumen['regionalizacion_pendiente'] > 0 ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-700' }} p-4 hover:border-emerald-300" href="{{ route('intelligence.reporte-mensual.datos-base', $seguimiento) }}">
                    <p class="text-xs font-semibold uppercase tracking-wide">Regionalización pendiente</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format($resumen['regionalizacion_pendiente'], 0, ',', '.') }}</p>
                </a>
            </div>
        </article>

        <aside class="panel">
            <h2 class="text-lg font-semibold">Pasiva vigente</h2>
            @if($seguimiento->pasivaVigente)
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500">Archivo</dt>
                        <dd class="mt-1 text-slate-900">{{ $seguimiento->pasivaVigente->nombre_original }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Carga</dt>
                        <dd class="mt-1 text-slate-900">{{ $seguimiento->pasivaVigente->lineas_total }} líneas · {{ $seguimiento->pasivaVigente->lineas_inversion }} de inversión</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Responsable</dt>
                        <dd class="mt-1 text-slate-900">{{ $seguimiento->pasivaVigente->subidoPor?->name ?? 'Sin dato' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Fecha</dt>
                        <dd class="mt-1 text-slate-900">{{ $seguimiento->pasivaVigente->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            @else
                <p class="mt-3 text-sm text-slate-500">Aún no hay pasiva cargada para este corte.</p>
            @endif

            @can('cargarPasiva', $seguimiento)
                <form method="post" action="{{ route('intelligence.reporte-mensual.pasivas.store', $seguimiento) }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                    @csrf
                    <label class="field">
                        <span>{{ $seguimiento->pasivaVigente ? 'Reemplazar pasiva' : 'Cargar pasiva' }}</span>
                        <input type="file" name="archivo" accept=".xlsx,.csv" required>
                    </label>
                    <button class="btn-primary w-full">Guardar pasiva</button>
                    <p class="text-xs text-slate-500">Una nueva carga reemplaza la vigente y recalcula los techos, conservando historial.</p>
                </form>
            @endcan
        </aside>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Proyectos para reportar</h2>
                <p class="text-sm text-slate-500">Entre por proyecto para diligenciar meta física, ejecución financiera, evidencias y regionalización del corte.</p>
            </div>
            <span class="text-sm font-semibold text-slate-500">{{ $filas->count() }} proyecto(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Proyecto</th>
                        <th class="px-4 py-3">Dependencia</th>
                        <th class="px-4 py-3 text-right" title="Valor programado en actividades">VP</th>
                        <th class="px-4 py-3 text-right" title="Reportado / comprometido en el corte">RD</th>
                        <th class="px-4 py-3 text-right">Techo</th>
                        <th class="px-4 py-3 text-right">Comprometido</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filas as $fila)
                        <tr>
                            <td class="max-w-xl px-4 py-3">
                                <p class="font-semibold text-slate-950">{{ $fila['proyecto']->nombre }}</p>
                                <p class="mt-1 text-xs text-slate-500">BPIN {{ $fila['proyecto']->bpin }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $fila['dependencia']->etiqueta() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($fila['valor_programado']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($fila['total']['reportado']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($fila['total']['techo']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($fila['total']['reportado']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right {{ $fila['total']['saldo'] < 0 ? 'text-red-700' : '' }}">{{ $pesos($fila['total']['saldo']) }}</td>
                            <td class="px-4 py-3"><span class="status {{ $fila['estado']->cssClass() }}">{{ $fila['estado']->label() }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <a class="btn-small" href="{{ route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $fila['proyecto'], 'dependencia' => $fila['dependencia']->id]) }}">Reportar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No hay proyectos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
