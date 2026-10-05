@extends('layouts.app', ['title' => $dependencia->nombre.' · Seguimiento a metas · SIID 2.0'])

@php
    use App\Filament\Pages\Workspace;

    $money = fn (float|int $value): string => '$'.number_format((float) $value, 0, ',', '.');
    $percent = fn (float|int $value): string => number_format((float) $value, 1, ',', '.').' %';
    $detailUrl = fn (string $relation): string => route('intelligence.seguimiento-dependencias.dependencia.detail', [
        'dependencia' => $dependencia,
        'relacion' => $relation,
    ]);
@endphp

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencias']) }}">← Dependencias</a>
            <p class="eyebrow mt-4">Seguimiento a metas · Hoja de vida</p>
            <h1 class="page-title">{{ $dependencia->nombre }}</h1>
            <p class="page-subtitle max-w-4xl">
                {{ $dependencia->codigo }}@if($dependencia->sigla) · {{ $dependencia->sigla }}@endif
                @if($seguimiento)
                    · Último corte: {{ $seguimiento->etiqueta() }} — {{ $seguimiento->fecha_corte->format('d/m/Y') }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ Workspace::getUrl(['workspace' => 'dependencias']) }}">Catálogo</a>
            <a class="btn-secondary" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia-reportar', 'record' => $dependencia->id]) }}">Reportar avances</a>
            @if($seguimiento)
                <a class="btn-primary" href="{{ Workspace::getUrl(['workspace' => 'seguimiento', 'record' => $seguimiento->id, 'dependencia' => $dependencia->id]) }}">Abrir reporte mensual</a>
            @endif
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Techo último corte</p>
            <p class="mt-2 break-words text-[clamp(1.35rem,2.1vw,2.25rem)] font-black leading-tight text-slate-950">
                @if($metricas['techo'] > 0)
                    <button type="button" class="count-detail-trigger font-black text-indigo-700" data-count-detail-url="{{ $detailUrl('techo') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($metricas['techo']) }}</button>
                @else
                    {{ $money(0) }}
                @endif
            </p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Comprometido</p>
            <p class="mt-2 break-words text-[clamp(1.35rem,2.1vw,2.25rem)] font-black leading-tight text-emerald-700">
                @if($metricas['comprometido'] > 0)
                    <button type="button" class="count-detail-trigger font-black text-emerald-700" data-count-detail-url="{{ $detailUrl('comprometido') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($metricas['comprometido']) }}</button>
                @else
                    {{ $money(0) }}
                @endif
            </p>
            <p class="mt-1 text-sm text-slate-500">{{ $percent($metricas['ejecucion_pct']) }} del techo</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Obligado / pagado</p>
            <p class="mt-2 break-words text-[clamp(1.25rem,1.9vw,1.875rem)] font-black leading-tight text-slate-950">
                @if($metricas['obligado'] > 0 || $metricas['pagado'] > 0)
                    <button type="button" class="count-detail-trigger font-black text-indigo-700" data-count-detail-url="{{ $detailUrl('obligado_pagado') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($metricas['obligado']) }}</button>
                @else
                    {{ $money(0) }}
                @endif
            </p>
            <p class="mt-1 break-words text-sm text-slate-500">Pagado {{ $money($metricas['pagado']) }}</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Avance físico</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight text-slate-950">
                @if($metricas['avance_fisico'] > 0)
                    <button type="button" class="count-detail-trigger font-black text-indigo-700" data-count-detail-url="{{ $detailUrl('avance_fisico') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $percent($metricas['avance_fisico_pct']) }}</button>
                @else
                    {{ $percent($metricas['avance_fisico_pct']) }}
                @endif
            </p>
            <p class="mt-1 break-words text-sm text-slate-500">
                {{ number_format((float) $metricas['avance_fisico'], 2, ',', '.') }} /
                @if($metricas['programacion_fisica'] > 0)
                    <button type="button" class="count-detail-trigger font-semibold text-indigo-700" data-count-detail-url="{{ $detailUrl('programacion_fisica') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ number_format((float) $metricas['programacion_fisica'], 2, ',', '.') }}</button>
                @else
                    0,00
                @endif
            </p>
        </article>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Metas resultado</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight">{{ number_format($metasResultado->count(), 0, ',', '.') }}</p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Metas producto</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight">
                @if($metasResultado->sum(fn ($grupo) => $grupo['metas_producto']->count()) > 0)
                    <button type="button" class="count-detail-trigger font-black text-indigo-700" data-count-detail-url="{{ $detailUrl('metas_producto') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ number_format($metasResultado->sum(fn ($grupo) => $grupo['metas_producto']->count()), 0, ',', '.') }}</button>
                @else
                    0
                @endif
            </p>
        </article>
        <article class="panel min-w-0 overflow-hidden">
            <p class="eyebrow">Proyectos reportados</p>
            <p class="mt-2 break-words text-[clamp(1.7rem,2.4vw,2.25rem)] font-black leading-tight">
                @if($metricas['proyectos_reportados'] > 0)
                    <button type="button" class="count-detail-trigger font-black text-indigo-700" data-count-detail-url="{{ $detailUrl('proyectos_reportados') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ number_format($metricas['proyectos_reportados'], 0, ',', '.') }}</button>
                @else
                    0
                @endif
            </p>
        </article>
    </section>

    <section class="panel">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Cadena PDD, metas producto y ODS aprobados</h2>
                <p class="mt-1 text-sm text-slate-500">Agrupado por meta resultado. Los ODS mostrados son únicamente relaciones confirmadas/aprobadas.</p>
            </div>
            <span class="status">{{ $metasResultado->count() }} metas resultado</span>
        </div>

        <div class="mt-5 space-y-4">
            @forelse($metasResultado as $grupo)
                @php($metaResultado = $grupo['meta_resultado'])
                <article class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Meta resultado</p>
                            <h3 class="mt-1 text-lg font-bold text-slate-950">
                                {{ $metaResultado?->codigo ?: 'Sin código' }} — {{ $metaResultado?->descripcion ?: 'Sin meta resultado relacionada' }}
                            </h3>
                            @if($grupo['indicador'])
                                <p class="mt-2 text-sm text-slate-600">
                                    Indicador: <strong>{{ $grupo['indicador']->codigo }}</strong> — {{ $grupo['indicador']->nombre }}
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{{ $grupo['metas_producto']->count() }} meta(s) producto</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{{ $grupo['proyectos']->count() }} proyecto(s)</span>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">{{ $grupo['ods']->count() }} ODS aprobado(s)</span>
                        </div>
                    </div>

                    @if($grupo['ods']->isNotEmpty())
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach($grupo['ods'] as $ods)
                                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">
                                        ODS {{ $ods['goal']?->code }} · {{ $ods['goal']?->name }}
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $ods['indicator']?->code }} — {{ $ods['indicator']?->name }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 overflow-x-auto">
                        <table data-siid-datatable class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Meta producto</th>
                                    <th class="px-3 py-2">Sector MGA</th>
                                    <th class="px-3 py-2">Proyectos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($grupo['metas_producto'] as $metaProducto)
                                    <tr>
                                        <td class="px-3 py-3 align-top">
                                            <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $metaProducto->id]) }}">
                                                {{ $metaProducto->codigo }}
                                            </a>
                                            <p class="mt-1 max-w-3xl text-slate-700">{{ $metaProducto->nombre }}</p>
                                        </td>
                                        <td class="px-3 py-3 align-top">{{ $metaProducto->sectorMga?->nombre ?? 'Sin sector MGA' }}</td>
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex flex-col gap-1">
                                                @forelse($metaProducto->proyectos as $proyecto)
                                                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->id]) }}">{{ $proyecto->bpin }}</a>
                                                @empty
                                                    <span class="text-slate-500">Sin proyectos vinculados</span>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-slate-500">Esta dependencia aún no tiene metas producto asociadas.</p>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold">Actividades del último reporte</h2>
                <p class="text-sm text-slate-500">Actividad, meta producto, BPIN y ejecución física/financiera del corte más reciente.</p>
            </div>
            <span class="status">{{ $actividades->count() }} actividad(es)</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Actividad</th>
                        <th class="px-4 py-3">Meta producto</th>
                        <th class="px-4 py-3">Proyecto</th>
                        <th class="px-4 py-3">Físico</th>
                        <th class="px-4 py-3">Comprometido</th>
                        <th class="px-4 py-3">Obligado</th>
                        <th class="px-4 py-3">Pagado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($actividades as $fila)
                        @php
                            $actividad = data_get($fila, 'actividad');
                            $metaProducto = data_get($fila, 'meta_producto');
                            $proyecto = data_get($fila, 'proyecto');
                            $actividadCodigo = $actividad?->codigo ?: ($actividad?->id ? 'ACT-'.$actividad->id : 'Actividad sin código');
                        @endphp
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <p class="font-semibold text-slate-950">{{ $actividadCodigo }}</p>
                                <p class="mt-1 max-w-xl text-slate-700">{{ $actividad?->nombre ?: 'Sin nombre de actividad' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $actividad?->origenLabel() ?: 'Sin origen registrado' }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                @if($metaProducto)
                                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $metaProducto->id]) }}">{{ $metaProducto->codigo }}</a>
                                    <p class="mt-1 max-w-md text-slate-600">{{ $metaProducto->nombre }}</p>
                                @else
                                    <span class="text-slate-500">Sin meta producto</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top">
                                @if($proyecto)
                                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->id]) }}">{{ $proyecto->bpin }}</a>
                                    <p class="mt-1 max-w-md text-slate-600">{{ $proyecto->nombre }}</p>
                                @else
                                    <span class="text-slate-500">Sin proyecto</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">
                                {{ number_format((float) $fila['avance'], 2, ',', '.') }} / {{ number_format((float) $fila['programado'], 2, ',', '.') }}
                                <p class="text-xs text-slate-500">{{ $percent($fila['programado'] > 0 ? ($fila['avance'] / $fila['programado']) * 100 : 0) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">{{ $money($fila['comprometido']) }}</td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">{{ $money($fila['obligado']) }}</td>
                            <td class="px-4 py-4 align-top whitespace-nowrap">{{ $money($fila['pagado']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay actividades registradas para esta dependencia en el último corte.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold">Proyectos del último reporte</h2>
                <p class="text-sm text-slate-500">Detalle físico y financiero por BPIN en el corte más reciente.</p>
            </div>
            <span class="status">{{ $reportes->count() }} reporte(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">BPIN / proyecto</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Físico</th>
                        <th class="px-4 py-3">Comprometido</th>
                        <th class="px-4 py-3">Obligado</th>
                        <th class="px-4 py-3">Pagado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reportes as $reporte)
                        @php
                            $programado = $reporte->avances->pluck('actividad')->filter()->unique('id')->sum(fn ($actividad) => (float) ($actividad->cantidad_programada ?? 0));
                            $avance = $reporte->avances->sum(fn ($item) => (float) $item->cantidad);
                        @endphp
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $reporte->proyecto_id]) }}">{{ $reporte->proyecto?->bpin }}</a>
                                <p class="mt-1 max-w-xl text-slate-700">{{ $reporte->proyecto?->nombre }}</p>
                            </td>
                            <td class="px-4 py-4 align-top"><span class="status {{ $reporte->estado->cssClass() }}">{{ $reporte->estado->label() }}</span></td>
                            <td class="px-4 py-4 align-top">
                                {{ number_format($avance, 2, ',', '.') }} / {{ number_format($programado, 2, ',', '.') }}
                                <p class="text-xs text-slate-500">{{ $percent($programado > 0 ? ($avance / $programado) * 100 : 0) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">{{ $money($reporte->ejecuciones->sum(fn ($item) => (float) $item->comprometido)) }}</td>
                            <td class="px-4 py-4 align-top">{{ $money($reporte->ejecuciones->sum(fn ($item) => (float) $item->obligado)) }}</td>
                            <td class="px-4 py-4 align-top">{{ $money($reporte->ejecuciones->sum(fn ($item) => (float) $item->pagado)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay reportes de proyectos para esta dependencia en el último corte.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@include('intelligence.catalogs.partials.count-detail-dialog', ['catalog' => new class { public function title(): string { return 'Seguimiento a metas'; } }])
@endsection
