@extends('layouts.app', ['title' => $project->bpin.' · Hoja de vida proyecto · SIID 2.0'])

@php
    $pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor);
@endphp

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ $projectsUrl }}">← Proyectos</a>
            <p class="eyebrow mt-4">Hoja de vida del proyecto · BPIN</p>
            <h1 class="page-title">{{ $project->bpin }}</h1>
            <p class="page-subtitle max-w-5xl">{{ $project->nombre }}</p>
        </div>
        <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-producto']) }}">Metas de producto</a>
    </header>

    <section class="grid gap-4 lg:grid-cols-4">
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Metas producto</p>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($summary['metas'], 0, ',', '.') }}</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Metas a reportar</p>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($summary['actividades'], 0, ',', '.') }}</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Techo registrado</p>
            <p class="mt-2 text-2xl font-bold text-slate-950">{{ $pesos($summary['techo']) }}</p>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reportes</p>
            <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($summary['reportes'], 0, ',', '.') }}</p>
        </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="panel lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sectores asociados</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse($project->dependencias as $dependencia)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $dependencia->codigo }} — {{ $dependencia->nombre }}</span>
                @empty
                    <span class="text-sm text-slate-500">Sin dependencias asociadas.</span>
                @endforelse
            </div>
        </article>
        <article class="panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Territorio / estado</p>
            <p class="mt-2 text-sm text-slate-700">Municipio: <strong>{{ $project->municipio?->nombre ?? 'No aplica' }}</strong></p>
            <p class="mt-1 text-sm text-slate-700">Focalización: <strong>{{ $project->tipo_focalizacion?->label() ?? 'Sin definir' }}</strong></p>
            <p class="mt-1 text-sm text-slate-700">Estado: <strong>{{ $project->activo ? 'Activo' : 'Inactivo' }}</strong></p>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Metas producto relacionadas</h2>
            <p class="text-sm text-slate-500">Metas del plan que alimenta este BPIN.</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($project->metasProducto as $meta)
                <article class="px-5 py-4">
                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $meta->getRouteKey()]) }}">
                        {{ $meta->codigo }} — {{ $meta->nombre }}
                    </a>
                    <p class="mt-2 text-sm text-slate-500">
                        Sector MGA: {{ $meta->sectorMga ? $meta->sectorMga->codigo.' — '.$meta->sectorMga->nombre : 'Sin sector' }}
                        @if($meta->metaResultado)
                            · Meta resultado: {{ $meta->metaResultado->codigo_provisional }}
                        @endif
                    </p>
                </article>
            @empty
                <p class="px-5 py-8 text-center text-slate-500">Este proyecto todavía no tiene metas producto asociadas.</p>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Registros de reporte por meta</h2>
            <p class="text-sm text-slate-500">Soporte técnico interno: una fila consolidada por proyecto, dependencia y meta producto.</p>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Registro / meta</th>
                        <th class="px-4 py-3">Dependencia</th>
                        <th class="px-4 py-3">Meta producto</th>
                        <th class="px-4 py-3 text-right">Programado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($project->actividades as $actividad)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $actividad->codigo ?: 'ACT-'.$actividad->id }}</p>
                                <p class="text-slate-600">{{ $actividad->nombre }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $actividad->dependencia?->codigo ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($actividad->metaProducto)
                                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $actividad->metaProducto->getRouteKey()]) }}">
                                        {{ $actividad->metaProducto->codigo }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $actividad->cantidad_programada, 2, ',', '.') }} {{ $actividad->unidad_medida }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay metas configuradas para reportar en este proyecto.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-semibold">Techos recientes</h2>
                <p class="text-sm text-slate-500">Últimos techos registrados por seguimiento, fuente y dependencia.</p>
            </div>
            <div class="overflow-x-auto">
                <table data-siid-datatable class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Seguimiento</th>
                            <th class="px-4 py-3">Fuente</th>
                            <th class="px-4 py-3">Dependencia</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($project->techos as $techo)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $techo->seguimiento?->etiqueta() ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $techo->fuente?->codigo ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $techo->dependencia?->codigo ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $pesos($techo->valor) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay techos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-semibold">Reportes recientes</h2>
                <p class="text-sm text-slate-500">Estado de los últimos reportes mensuales asociados al BPIN.</p>
            </div>
            <div class="overflow-x-auto">
                <table data-siid-datatable class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Seguimiento</th>
                            <th class="px-4 py-3">Dependencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Fecha reporte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($project->reportes as $reporte)
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $reporte->seguimiento?->etiqueta() ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $reporte->dependencia?->codigo ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $reporte->estado?->label() ?? $reporte->estado?->value ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $reporte->reportado_at?->format('d/m/Y H:i') ?? 'Sin reportar' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay reportes mensuales para este proyecto.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</div>
@endsection
