@extends('layouts.app', ['title' => 'Proyectos · Seguimiento a metas · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Proyectos</h1>
            <p class="page-subtitle max-w-4xl">Portafolio BPIN usado por el seguimiento mensual, metas producto y reportes sectoriales.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-producto']) }}">Ver metas de producto</a>
            @if(auth()->user()?->canManageIntelligenceCatalogs())
                <a class="btn-primary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyectos-importar']) }}">Importar relaciones</a>
            @endif
        </div>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Listado de proyectos</h2>
                <p class="text-sm text-slate-500">{{ number_format($projects->count(), 0, ',', '.') }} proyecto(s) encontrados.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable data-search-placeholder="Buscar BPIN, proyecto, dependencia, municipio o estado…" class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">BPIN / Proyecto</th>
                        <th class="px-4 py-3" data-filter="select" data-filter-label="Dependencia">Dependencias</th>
                        <th class="px-4 py-3">Municipio</th>
                        <th class="px-4 py-3 text-right">Metas</th>
                        <th class="px-4 py-3 text-right">Metas a reportar</th>
                        <th class="px-4 py-3 text-right">Reportes</th>
                        <th class="px-4 py-3" data-filter="select" data-filter-label="Estado">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($projects as $project)
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-4 py-4">
                                <a class="font-semibold text-indigo-700 hover:underline" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyecto', 'record' => $project->getRouteKey()]) }}">
                                    {{ $project->bpin }}
                                </a>
                                <p class="mt-1 max-w-2xl text-slate-700">{{ $project->nombre }}</p>
                            </td>
                            @php($dependenciaFiltros = $project->dependencias->map(fn ($dependencia) => $dependencia->codigo ?: $dependencia->nombre)->filter()->implode('|'))
                            <td class="px-4 py-4 text-slate-600" data-filter-values="{{ $dependenciaFiltros ?: 'Sin dependencia' }}">
                                {{ $project->dependencias->map(fn ($dependencia) => $dependencia->codigo ?: $dependencia->nombre)->implode(' · ') ?: 'Sin dependencia' }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">{{ $project->municipio?->nombre ?? '—' }}</td>
                            <td class="px-4 py-4 text-right font-semibold">{{ number_format($project->metas_producto_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-4 text-right font-semibold">{{ number_format($project->actividades_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-4 text-right font-semibold">{{ number_format($project->reportes_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-4" data-filter-values="{{ $project->activo ? 'Activo' : 'Inactivo' }}">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $project->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $project->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No hay proyectos con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
