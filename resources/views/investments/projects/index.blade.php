@extends('layouts.app')

@section('content')
@php($universeLabels = ['governor' => 'Gobernación del Meta', 'territory' => 'Territorio Meta', 'ecosystem' => 'Ecosistema territorial'])
<div>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><a href="{{ route('investments.dashboard', ['universe' => $filters['universe'], 'period_mode' => $filters['period_mode']]) }}" class="text-sm text-indigo-600">← Panorama</a><h1 class="page-title">Portafolio de proyectos</h1><p class="page-subtitle">{{ $universeLabels[$filters['universe']] }} · Gobierno 2024–2027 · {{ $filters['period_mode'] === 'execution' ? 'ejecución por vigencia' : 'horizonte coincidente' }}.</p></div>
        <div class="flex gap-2"><a class="btn-small {{ $filters['period_mode'] === 'execution' ? 'bg-indigo-600 text-white' : '' }}" href="{{ route('investments.projects.index', ['universe' => $filters['universe'], 'period_mode' => 'execution']) }}">Ejecución</a><a class="btn-small {{ $filters['period_mode'] === 'horizon' ? 'bg-indigo-600 text-white' : '' }}" href="{{ route('investments.projects.index', ['universe' => $filters['universe'], 'period_mode' => 'horizon']) }}">Horizonte</a></div>
    </div>
    <form method="get" class="mt-7 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-4">
        <input type="hidden" name="universe" value="{{ $filters['universe'] }}">
        <input type="hidden" name="period_mode" value="{{ $filters['period_mode'] }}">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="BPIN, nombre o entidad">
        <select name="municipality"><option value="">Todos los municipios</option>@foreach($municipalities as $code => $name)<option value="{{ $code }}" @selected(($filters['municipality'] ?? '') === $code)>{{ $name }}</option>@endforeach</select>
        <select name="sector"><option value="">Todos los sectores</option>@foreach($sectors as $sector)<option @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>@endforeach</select>
        <select name="status"><option value="">Todos los estados</option>@foreach($statuses as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
        <select name="entity"><option value="">Todas las entidades</option>@foreach($entities as $entity)<option @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>@endforeach</select>
        <select name="funding_source"><option value="">Todas las fuentes</option>@foreach($fundingSources as $source)<option @selected(($filters['funding_source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select>
        <input type="number" name="year" value="{{ $filters['year'] ?? '' }}" placeholder="Vigencia" min="1990" max="2200">
        <select name="project_type"><option value="">Todos los tipos</option>@foreach(['PGN','T','SGR'] as $type)<option @selected(($filters['project_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="btn-primary grow">Filtrar</button><a href="{{ route('investments.projects.index', ['universe' => $filters['universe'], 'period_mode' => $filters['period_mode']]) }}" class="btn-secondary">Limpiar</a></div>
    </form>

    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">BPIN / proyecto</th><th class="px-4 py-3">Municipio</th><th class="px-4 py-3">Sector</th><th class="px-4 py-3">Entidad</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Valor total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $project)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-4"><a class="font-semibold text-indigo-700 hover:underline" href="{{ route('investments.projects.show', $project) }}">{{ $project->bpin }}</a><p class="mt-1 max-w-xl text-slate-700">{{ $project->name ?? 'Nombre no reportado' }}</p></td>
                        <td class="px-4 py-4 text-slate-600">{{ $project->locations->pluck('municipality')->filter()->unique()->take(3)->implode(', ') ?: 'No reportado' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $project->sector ?? 'No reportado' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $project->responsible_entity ?? 'No reportado' }}</td>
                        <td class="px-4 py-4"><span class="status">{{ $project->status ?? 'No reportado' }}</span></td>
                        <td class="px-4 py-4 text-right font-medium">{{ $project->total_value === null ? 'No reportado' : '$'.number_format((float)$project->total_value, 0, ',', '.') }}</td>
                    </tr>
                @empty <tr><td colspan="6" class="p-10 text-center text-slate-500">No hay proyectos para estos filtros. Ejecute una actualización si el módulo está vacío.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $projects->links() }}</div>
</div>
@endsection
