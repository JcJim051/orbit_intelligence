@extends('layouts.app', ['title' => $dependencia->codigo.' · Detalle de dependencia · SIID 2.0'])

@section('content')
@php
    $proyectosRelacionados = $dependencia->proyectos
        ->merge($dependencia->metasProducto->flatMap->proyectos)
        ->unique('id')
        ->sortBy('bpin')
        ->values();
@endphp
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a class="text-sm font-semibold text-emerald-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'dependencias']) }}">← Volver a dependencias</a>
            <p class="eyebrow mt-4">Detalle de dependencia</p>
            <h1 class="page-title">{{ $dependencia->codigo }}</h1>
            <p class="page-subtitle max-w-4xl">{{ $dependencia->nombre }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $dependencia)
                <a class="btn-secondary" href="{{ route('intelligence.dependencias.edit', $dependencia) }}">Editar</a>
            @endcan
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel py-4">
            <p class="eyebrow">Tipo</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $dependencia->tipo?->label() ?? 'Sin tipo' }}</p>
        </article>
        <article class="panel py-4">
            <p class="eyebrow">Metas resultado</p>
            <p class="mt-2 text-xl font-black text-indigo-700">{{ number_format($metasResultado->where('meta')->count(), 0, ',', '.') }}</p>
        </article>
        <article class="panel py-4">
            <p class="eyebrow">Metas producto</p>
            <p class="mt-2 text-xl font-black text-indigo-700">{{ number_format($dependencia->metasProducto->count(), 0, ',', '.') }}</p>
        </article>
        <article class="panel py-4">
            <p class="eyebrow">Proyectos relacionados</p>
            <p class="mt-2 text-xl font-black text-indigo-700">{{ number_format($proyectosRelacionados->count(), 0, ',', '.') }}</p>
        </article>
    </section>

    <section class="panel">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Nombre oficial</p>
                <p class="mt-1 text-sm text-slate-950">{{ $dependencia->nombre }}</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sigla</p>
                <p class="mt-1 text-sm text-slate-950">{{ $dependencia->sigla ?: 'Sin sigla' }}</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Hoja de matriz</p>
                <p class="mt-1 text-sm text-slate-950">{{ $dependencia->hoja_matriz ?: 'Sin hoja asociada' }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <article class="panel">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-950">Equipo asociado</h2>
                <span class="status">{{ number_format($dependencia->usuarios->count(), 0, ',', '.') }} usuario(s)</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($dependencia->usuarios as $usuario)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $usuario->name }}</span>
                @empty
                    <span class="text-sm text-slate-500">Sin usuarios asignados.</span>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-950">Proyectos asociados</h2>
                <span class="status">{{ number_format($proyectosRelacionados->count(), 0, ',', '.') }} BPIN</span>
            </div>
            <div class="mt-4 max-h-56 space-y-3 overflow-y-auto pr-1">
                @forelse($proyectosRelacionados as $proyecto)
                    <div>
                        <a class="font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->getRouteKey()]) }}">{{ $proyecto->bpin }}</a>
                        <p class="line-clamp-2 text-xs text-slate-500">{{ $proyecto->nombre }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Sin proyectos vinculados.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Metas resultado y productos asociados</h2>
            <p class="mt-1 text-sm text-slate-500">Cada bloque agrupa las metas producto de esta dependencia según su meta resultado del PDD.</p>
        </div>

        @forelse($metasResultado as $grupo)
            @php($metaResultado = $grupo['meta'])
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                    @if($metaResultado)
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $metaResultado->codigo_provisional ?? 'Meta resultado' }}</p>
                                <h3 class="mt-1 text-base font-black text-slate-950">{{ $metaResultado->descripcion }}</h3>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ $metaResultado->subprograma?->nombre
                                        ?? $metaResultado->programa?->nombre
                                        ?? 'Sin ubicación PDD' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-slate-200">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Indicador resultado</p>
                                <p class="mt-1 max-w-md font-semibold text-slate-900">{{ $metaResultado->indicador?->nombre ?? 'Sin indicador resultado' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm font-semibold text-slate-950">Metas producto sin meta resultado asociada</p>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-white text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Meta producto</th>
                                <th class="px-5 py-3">Indicador producto</th>
                                <th class="px-5 py-3">Sector MGA</th>
                                <th class="px-5 py-3">Proyectos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($grupo['metas_producto'] as $metaProducto)
                                <tr>
                                    <td class="px-5 py-4 align-top">
                                        <a class="font-bold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'meta-producto', 'record' => $metaProducto->getRouteKey()]) }}">
                                            {{ $metaProducto->codigo }}
                                        </a>
                                        <p class="mt-1 max-w-xl text-slate-700">{{ $metaProducto->nombre }}</p>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-700">
                                        {{ $metaProducto->indicadorProducto?->nombre ?? ($metaProducto->indicador_producto ?: 'Sin indicador producto') }}
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-700">
                                        {{ $metaProducto->sectorMga?->nombre ?? 'Sin sector MGA' }}
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        @forelse($metaProducto->proyectos as $proyecto)
                                            <a class="mb-1 block font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $proyecto->getRouteKey()]) }}">
                                                {{ $proyecto->bpin }}
                                            </a>
                                            <p class="mb-2 line-clamp-2 max-w-md text-xs text-slate-500">{{ $proyecto->nombre }}</p>
                                        @empty
                                            <span class="text-slate-500">Sin BPIN vinculado</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-slate-500">Esta dependencia todavía no tiene metas producto asociadas.</p>
        @endforelse
    </section>
</div>
@endsection
