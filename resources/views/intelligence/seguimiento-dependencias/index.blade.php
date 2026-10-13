@extends('layouts.app', ['title' => 'Dependencias en seguimiento · SIID 2.0'])

@php
    use App\Filament\Pages\Workspace;

    $money = fn (float|int $value): string => '$'.number_format((float) $value, 0, ',', '.');
    $percent = fn (float|int $value): string => number_format((float) $value, 1, ',', '.').' %';
    $indexDetailUrl = fn (string $relation): string => route('intelligence.seguimiento-dependencias.detail', array_filter([
        'relacion' => $relation,
        'q' => $filtros['q'] ?: null,
    ]));
    $dependencyDetailUrl = fn ($dependencia, string $relation): string => route('intelligence.seguimiento-dependencias.dependencia.detail', [
        'dependencia' => $dependencia,
        'relacion' => $relation,
    ]);
@endphp

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas · Dependencias</p>
            <h1 class="page-title">Hoja de vida por dependencia</h1>
            <p class="page-subtitle max-w-4xl">
                Consulte la información operativa de cada dependencia: metas resultado, metas producto, proyectos,
                relaciones ODS aprobadas y ejecución física/financiera del corte más reciente.
            </p>
            @if($seguimiento)
                <p class="mt-2 text-sm text-slate-500">Corte usado: <strong>{{ $seguimiento->etiqueta() }}</strong> · {{ $seguimiento->fecha_corte->format('d/m/Y') }}</p>
            @else
                <p class="mt-2 text-sm text-amber-700">Aún no hay seguimientos mensuales registrados.</p>
            @endif
        </div>
        <a class="btn-secondary" href="{{ Workspace::getUrl(['workspace' => 'dependencias']) }}">Administrar catálogo</a>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel min-w-0">
            <p class="eyebrow">Dependencias visibles</p>
            <p class="mt-2 min-w-0 break-all text-[clamp(1.05rem,1.55vw,1.75rem)] font-black leading-tight text-slate-950">
                @if($dependencias->count() > 0)
                    <button type="button" class="count-detail-trigger block max-w-full break-all text-left font-black text-indigo-700" data-count-detail-url="{{ $indexDetailUrl('dependencias') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ number_format($dependencias->count(), 0, ',', '.') }}</button>
                @else
                    0
                @endif
            </p>
        </article>
        <article class="panel min-w-0">
            <p class="eyebrow">Proyectos reportados</p>
            <p class="mt-2 min-w-0 break-all text-[clamp(1.05rem,1.55vw,1.75rem)] font-black leading-tight text-slate-950">
                @if($metricas->sum('proyectos_reportados') > 0)
                    <button type="button" class="count-detail-trigger block max-w-full break-all text-left font-black text-indigo-700" data-count-detail-url="{{ $indexDetailUrl('proyectos_reportados') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ number_format($metricas->sum('proyectos_reportados'), 0, ',', '.') }}</button>
                @else
                    0
                @endif
            </p>
        </article>
        <article class="panel min-w-0">
            <p class="eyebrow">Techo último corte</p>
            <p class="mt-2 min-w-0 break-all text-[clamp(1.05rem,1.55vw,1.75rem)] font-black leading-tight text-slate-950">
                @if($metricas->sum('techo') > 0)
                    <button type="button" class="count-detail-trigger block max-w-full break-all text-left font-black text-indigo-700" data-count-detail-url="{{ $indexDetailUrl('techo') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($metricas->sum('techo')) }}</button>
                @else
                    {{ $money(0) }}
                @endif
            </p>
        </article>
        <article class="panel min-w-0">
            <p class="eyebrow">Comprometido</p>
            <p class="mt-2 min-w-0 break-all text-[clamp(1.05rem,1.55vw,1.75rem)] font-black leading-tight text-emerald-700">
                @if($metricas->sum('comprometido') > 0)
                    <button type="button" class="count-detail-trigger block max-w-full break-all text-left font-black text-emerald-700" data-count-detail-url="{{ $indexDetailUrl('comprometido') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($metricas->sum('comprometido')) }}</button>
                @else
                    {{ $money(0) }}
                @endif
            </p>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold">Dependencias</h2>
                <p class="text-sm text-slate-500">Abra una dependencia para ver su hoja de vida completa.</p>
            </div>
            @if(! $puedeVerTodo)
                <span class="status">Solo sus dependencias asignadas</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable class="seguimiento-dependencias-table w-full table-fixed text-xs">
                <colgroup>
                    <col class="w-[32%]">
                    <col class="w-[12%]">
                    <col class="w-[16%]">
                    <col class="w-[16%]">
                    <col class="w-[14%]">
                    <col class="w-[10%]">
                </colgroup>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Dependencia</th>
                        <th class="px-4 py-3">Metas producto</th>
                        <th class="px-4 py-3">Techo</th>
                        <th class="px-4 py-3">Ejecución</th>
                        <th class="px-4 py-3">Avance físico</th>
                        <th class="px-4 py-3">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dependencias as $dependencia)
                        @php($fila = $metricas->get($dependencia->id, []))
                        <tr>
                            <td class="px-4 py-4 align-top" data-label="Dependencia">
                                <p class="font-semibold text-slate-950">{{ $dependencia->sigla ?: $dependencia->codigo }}</p>
                                <p class="mt-1 max-w-xl text-slate-600">{{ $dependencia->nombre }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Metas producto">
                                <x-reporte-sectorial.conteo :conteo="(int) $dependencia->metas_producto_count" :url="$dependencyDetailUrl($dependencia, 'metas_producto')" titulo="Ver metas producto relacionadas" />
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Techo">
                                @if(($fila['techo'] ?? 0) > 0)
                                    <button type="button" class="count-detail-trigger font-semibold text-indigo-700" data-count-detail-url="{{ $dependencyDetailUrl($dependencia, 'techo') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($fila['techo'] ?? 0) }}</button>
                                @else
                                    <span class="text-slate-400">{{ $money(0) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Ejecución">
                                <p class="font-semibold">
                                    @if(($fila['comprometido'] ?? 0) > 0)
                                        <button type="button" class="count-detail-trigger font-semibold text-indigo-700" data-count-detail-url="{{ $dependencyDetailUrl($dependencia, 'comprometido') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $money($fila['comprometido'] ?? 0) }}</button>
                                    @else
                                        <span class="text-slate-400">{{ $money(0) }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500">{{ $percent($fila['ejecucion_pct'] ?? 0) }}</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Avance físico">
                                <p class="font-semibold">
                                    @if(($fila['avance_fisico_pct'] ?? 0) > 0)
                                        <button type="button" class="count-detail-trigger font-semibold text-indigo-700" data-count-detail-url="{{ $dependencyDetailUrl($dependencia, 'avance_fisico') }}" aria-haspopup="dialog" aria-controls="count-detail-dialog">{{ $percent($fila['avance_fisico_pct'] ?? 0) }}</button>
                                    @else
                                        <span class="text-slate-400">{{ $percent(0) }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500">Promedio por meta</p>
                            </td>
                            <td class="px-4 py-4 align-top" data-label="Acción">
                                <div class="flex flex-col gap-2">
                                    <a class="font-semibold text-indigo-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $dependencia->id]) }}">Ver hoja de vida</a>
                                    <a class="font-semibold text-emerald-700 hover:underline" href="{{ Workspace::getUrl(['workspace' => 'seguimiento-dependencia-reportar', 'record' => $dependencia->id]) }}">Reportar avances</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay dependencias con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@include('intelligence.catalogs.partials.count-detail-dialog', ['catalog' => new class { public function title(): string { return 'Seguimiento a metas'; } }])
@endsection
