@extends('layouts.app')

@section('content')
@php($money = fn ($value) => $value === null ? 'Datos no reportados' : '$'.number_format((float) $value, 0, ',', '.'))
<div class="space-y-7">
    <div>
        <a href="{{ route('investments.dashboard', ['universe' => 'governor', 'period_mode' => $filters['period_mode']]) }}" class="text-sm text-indigo-600">← Entidades descentralizadas</a>
        <p class="eyebrow mt-4">{{ $entity->acronym ?: 'Entidad descentralizada' }}</p>
        <h1 class="page-title">{{ $entity->name }}</h1>
        <p class="page-subtitle">Gobierno departamental 2024–2027 · {{ $filters['period_mode'] === 'execution' ? 'ejecución por vigencia' : 'horizonte coincidente' }}.</p>
    </div>

    <section class="panel border-indigo-200 bg-indigo-50/40">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <p class="text-sm text-indigo-900">Las cifras suman únicamente asignaciones principales confirmadas. Hay <strong>{{ number_format($suggestedCount) }}</strong> sugerencias pendientes de revisión para esta entidad.</p>
            <div class="flex gap-2"><a class="btn-small {{ $filters['period_mode'] === 'execution' ? 'bg-indigo-600 text-white' : 'bg-white' }}" href="{{ route('investments.entities.show', ['investmentEntity' => $entity, 'period_mode' => 'execution']) }}">Ejecución 2024–2027</a><a class="btn-small {{ $filters['period_mode'] === 'horizon' ? 'bg-indigo-600 text-white' : 'bg-white' }}" href="{{ route('investments.entities.show', ['investmentEntity' => $entity, 'period_mode' => 'horizon']) }}">Horizonte coincidente</a></div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Proyectos confirmados', number_format($summary['projects'])],
            ['Valor total', $money($summary['total_value'])],
            ['Vigente 2024–2027', $money($summary['current_value'])],
            ['Pagado 2024–2027', $money($summary['paid_value'])],
            ['Comprometido', $money($summary['committed_value'])],
            ['Obligado', $money($summary['obligated_value'])],
            ['Pagado / vigente', $summary['financial_execution_percent'] === null ? 'No calculable' : number_format($summary['financial_execution_percent'], 1, ',', '.').'%'],
        ] as [$label, $value])
            <article class="panel"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-semibold tracking-tight">{{ $value }}</p></article>
        @endforeach
    </div>

    <form method="get" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5">
        <input type="hidden" name="period_mode" value="{{ $filters['period_mode'] }}">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="BPIN o nombre">
        <select name="municipality"><option value="">Todos los municipios</option>@foreach($municipalities as $code => $name)<option value="{{ $code }}" @selected(($filters['municipality'] ?? '') === $code)>{{ $name }}</option>@endforeach</select>
        <select name="sector"><option value="">Todos los sectores</option>@foreach($sectors as $sector)<option @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>@endforeach</select>
        <select name="status"><option value="">Todos los estados</option>@foreach($statuses as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="btn-primary grow">Filtrar</button><a class="btn-secondary" href="{{ route('investments.entities.show', ['investmentEntity' => $entity, 'period_mode' => $filters['period_mode']]) }}">Limpiar</a></div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">BPIN / proyecto</th><th class="px-4 py-3">Municipio</th><th class="px-4 py-3">Sector</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Alertas</th><th class="px-4 py-3 text-right">Valor total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $project)
                    <tr><td class="px-4 py-4"><a class="font-semibold text-indigo-700 hover:underline" href="{{ route('investments.projects.show', $project) }}">{{ $project->bpin }}</a><p class="mt-1 max-w-xl text-slate-700">{{ $project->name ?? 'Nombre no reportado' }}</p></td><td class="px-4 py-4 text-slate-600">{{ $project->locations->pluck('municipality')->filter()->unique()->take(3)->implode(', ') ?: 'No reportado' }}</td><td class="px-4 py-4 text-slate-600">{{ $project->sector ?? 'No reportado' }}</td><td class="px-4 py-4"><span class="status">{{ $project->status ?? 'No reportado' }}</span></td><td class="px-4 py-4"><span class="{{ $project->alerts_count ? 'text-amber-700' : 'text-slate-500' }}">{{ $project->alerts_count }}</span></td><td class="px-4 py-4 text-right font-medium">{{ $money($project->total_value) }}</td></tr>
                @empty <tr><td colspan="6" class="p-10 text-center text-slate-500">No hay proyectos confirmados para estos filtros. Las sugerencias pueden revisarse desde el panel administrativo.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    {{ $projects->links() }}
</div>
@endsection
