@extends('layouts.app', ['title' => 'Metas resultado por pilar · SIID 2.0'])

@php($numero = fn ($valor) => $valor === null ? '—' : rtrim(rtrim(number_format((float) $valor, 4, ',', '.'), '0'), ','))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Metas resultado por pilar</h1>
            <p class="page-subtitle max-w-3xl">
                Consulta transversal de las metas de resultado agrupadas por pilar del Plan de Desarrollo. Sirve para revisar rápidamente qué programas, indicadores y metas producto están asociados a cada pilar.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('intelligence.metas-resultado.por-pilar.download', request()->query()) }}">Descargar tabla</a>
            <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-resultado']) }}">Ver catálogo editable</a>
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-4">
        <article class="panel">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Pilares con metas</span>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $totalPilares }}</p>
        </article>
        <article class="panel">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Metas resultado</span>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $totalMetas }}</p>
        </article>
        <article class="panel">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Programas vinculados</span>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $totalProgramas }}</p>
        </article>
        <article class="panel">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Metas producto asociadas</span>
            <p class="mt-2 text-3xl font-black text-slate-950">{{ $totalMetasProducto }}</p>
        </article>
    </section>

    <section class="panel">
        <form method="get" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="field xl:col-span-2">
                <span>Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Meta, indicador, programa o subprograma">
            </label>
            <label class="field">
                <span>Pilar</span>
                <select name="pilar_id">
                    <option value="">Todos</option>
                    @foreach($pilares as $pilar)
                        <option value="{{ $pilar->id }}" @selected($filters['pilar_id'] === (string) $pilar->id)>{{ $pilar->numeral }} — {{ $pilar->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="activo">
                    <option value="" @selected($filters['activo'] === '')>Todos</option>
                    <option value="1" @selected($filters['activo'] === '1')>Activas</option>
                    <option value="0" @selected($filters['activo'] === '0')>Inactivas</option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="btn-primary">Filtrar</button>
                <a class="btn-secondary" href="{{ url()->current() }}">Limpiar</a>
                <a class="btn-secondary" href="{{ route('intelligence.metas-resultado.por-pilar.download', request()->query()) }}">Descargar</a>
            </div>
        </form>
    </section>

    <section class="space-y-4">
        @forelse($grupos as $grupo)
            @php($pilar = $grupo['pilar'])
            <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" open>
                <summary class="cursor-pointer list-none border-b border-slate-100 bg-slate-50 px-5 py-4 marker:hidden">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-700">{{ $pilar ? 'Pilar '.$pilar->numeral : 'Sin pilar relacionado' }}</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $pilar?->nombre ?? 'Metas pendientes de clasificar' }}</h2>
                            @if($pilar)
                                <p class="mt-1 text-sm text-slate-500">{{ $pilar->codigo }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                            <span class="rounded-full bg-white px-3 py-1">{{ $grupo['metas']->count() }} meta(s)</span>
                            <span class="rounded-full bg-white px-3 py-1">{{ $grupo['programas'] }} programa(s)</span>
                            <span class="rounded-full bg-white px-3 py-1">{{ $grupo['metas_producto'] }} meta(s) producto</span>
                        </div>
                    </div>
                </summary>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-white text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Meta resultado</th>
                                <th class="px-4 py-3">Indicador</th>
                                <th class="px-4 py-3">Programa / Subprograma</th>
                                <th class="px-4 py-3 text-right">Línea base</th>
                                <th class="px-4 py-3 text-right">Meta cuatrienio</th>
                                <th class="px-4 py-3 text-right">Metas producto</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($grupo['metas'] as $meta)
                                @php($programa = $meta->subprograma?->programa ?? $meta->programa)
                                <tr>
                                    <td class="max-w-md px-4 py-3 align-top">
                                        <span class="mb-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Meta resultado PDD</span>
                                        <span class="block font-semibold text-slate-950">{{ $meta->codigo_provisional ?? $meta->codigo ?? 'Sin código' }}</span>
                                        <span class="mt-1 block text-slate-600">{{ $meta->descripcion }}</span>
                                    </td>
                                    <td class="max-w-sm px-4 py-3 align-top">
                                        @if($meta->indicador)
                                            <span class="mb-2 inline-flex rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-indigo-700">Indicador de resultado PDD</span>
                                            <span class="block font-semibold text-slate-900">{{ $meta->indicador->nombre }}</span>
                                            <span class="text-xs text-slate-500">{{ $meta->indicador->unidad_medida }}</span>
                                        @else
                                            <span class="text-slate-400">Sin indicador</span>
                                        @endif
                                    </td>
                                    <td class="max-w-sm px-4 py-3 align-top">
                                        <span class="block font-semibold text-slate-900">{{ $programa?->numeral }} {{ $programa?->nombre ?? 'Sin programa' }}</span>
                                        @if($meta->subprograma)
                                            <span class="mt-1 block text-xs text-slate-500">Subprograma: {{ $meta->subprograma->numeral }} {{ $meta->subprograma->nombre }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $numero($meta->linea_base) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $numero($meta->meta_cuatrienio) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                        @if($meta->metas_producto_count > 0)
                                            <button
                                                type="button"
                                                class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-4"
                                                data-open-modal="metas-producto-{{ $meta->id }}"
                                            >
                                                {{ $meta->metas_producto_count }} · ver
                                            </button>
                                        @else
                                            <span class="text-slate-400">0</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top"><span class="status {{ $meta->activo ? 'status-approved' : '' }}">{{ $meta->activo ? 'Activa' : 'Inactiva' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @empty
            <article class="panel text-center text-slate-500">
                No hay metas de resultado con los filtros seleccionados.
            </article>
        @endforelse
    </section>

    @foreach($grupos as $grupo)
        @foreach($grupo['metas'] as $meta)
            @if($meta->metas_producto_count > 0)
                <div id="metas-producto-{{ $meta->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" data-modal>
                    <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                            <div>
                                <p class="eyebrow">Metas producto contadas</p>
                                <h3 class="text-lg font-black text-slate-950">{{ $meta->codigo_provisional ?? $meta->codigo ?? 'Meta resultado' }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $meta->metas_producto_count }} meta(s) producto asociada(s) a esta meta resultado.</p>
                            </div>
                            <button type="button" class="rounded-full px-3 py-1 text-xl text-slate-500 hover:bg-slate-100" data-close-modal="metas-producto-{{ $meta->id }}">×</button>
                        </div>
                        <div class="max-h-[65vh] overflow-y-auto p-5">
                            <div class="space-y-3">
                                @foreach($meta->metasProducto as $metaProducto)
                                    <article class="rounded-xl border border-slate-200 p-3">
                                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                            <div>
                                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">Meta producto</span>
                                                <p class="mt-2 font-semibold text-slate-950">{{ $metaProducto->codigo ?? 'Sin código' }} · {{ $metaProducto->nombre }}</p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $metaProducto->dependencia?->nombre ?? 'Sin dependencia' }}
                                                    @if($metaProducto->sectorMga)
                                                        · Sector MGA: {{ $metaProducto->sectorMga->nombre }}
                                                    @endif
                                                </p>
                                            </div>
                                            <span class="status {{ $metaProducto->activo ? 'status-approved' : '' }}">{{ $metaProducto->activo ? 'Activa' : 'Inactiva' }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach
</div>

<script>
    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-modal]');
        if (openButton) {
            document.getElementById(openButton.dataset.openModal)?.classList.remove('hidden');
            document.getElementById(openButton.dataset.openModal)?.classList.add('flex');
        }

        const closeButton = event.target.closest('[data-close-modal]');
        if (closeButton) {
            document.getElementById(closeButton.dataset.closeModal)?.classList.add('hidden');
            document.getElementById(closeButton.dataset.closeModal)?.classList.remove('flex');
        }

        if (event.target.matches('[data-modal]')) {
            event.target.classList.add('hidden');
            event.target.classList.remove('flex');
        }
    });
</script>
@endsection
