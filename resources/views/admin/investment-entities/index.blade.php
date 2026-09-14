@extends('layouts.app')

@section('content')
<div class="space-y-7">
    <div><p class="eyebrow">Administración</p><h1 class="page-title">Entidades descentralizadas</h1><p class="page-subtitle">Catálogo oficial y revisión de asociaciones con proyectos BPIN.</p></div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($entities as $entity)
            <details class="panel">
                <summary class="cursor-pointer list-none"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold text-indigo-600">{{ $entity->acronym ?: 'Entidad' }}</p><h2 class="mt-1 font-semibold">{{ $entity->name }}</h2></div><span class="status {{ $entity->active ? 'status-approved' : '' }}">{{ $entity->active ? 'Activa' : 'Inactiva' }}</span></div><p class="mt-3 text-xs text-slate-500">{{ $entity->confirmed_count }} confirmados · {{ $entity->suggested_count }} sugeridos</p></summary>
                <form method="post" action="{{ route('admin.investment-entities.update', $entity) }}" class="mt-5 space-y-3">@csrf @method('PATCH')
                    <label class="field"><span>Nombre oficial</span><input name="name" value="{{ $entity->name }}" required></label>
                    <label class="field"><span>Sigla</span><input name="acronym" value="{{ $entity->acronym }}"></label>
                    <label class="field"><span>Alias, uno por línea</span><textarea name="aliases" rows="4" required>{{ implode("\n", $entity->aliases) }}</textarea></label>
                    <label class="field"><span>Fuente oficial</span><input type="url" name="source_url" value="{{ $entity->source_url }}" required></label>
                    <label class="field"><span>Orden</span><input type="number" name="sort_order" value="{{ $entity->sort_order }}" min="0" max="1000" required></label>
                    <label class="check"><input type="checkbox" name="active" value="1" @checked($entity->active)> Entidad visible</label>
                    <button class="btn-primary w-full">Guardar entidad</button>
                </form>
            </details>
        @endforeach
    </section>

    <section class="panel">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 class="text-lg font-semibold">Clasificaciones de proyectos</h2><p class="mt-1 text-sm text-slate-500">Confirme, reasigne o rechace cada propuesta. Todas las decisiones quedan auditadas.</p></div><div class="flex flex-wrap gap-2">@foreach(['suggested' => 'Pendientes', 'unclassified' => 'Por clasificar', 'confirmed' => 'Confirmadas', 'rejected' => 'Rechazadas'] as $value => $label)<a class="btn-small {{ $status === $value ? 'bg-indigo-600 text-white' : '' }}" href="{{ route('admin.investment-entities.index', ['status' => $value]) }}">{{ $label }}</a>@endforeach</div></div>
        <div class="mt-5 space-y-4">
            @if($status === 'unclassified')
                @forelse($unclassifiedProjects as $project)
                    <article class="rounded-xl border border-slate-200 p-4"><div class="grid gap-4 xl:grid-cols-[1fr_auto] xl:items-end"><div><a class="font-semibold text-indigo-700" href="{{ route('investments.projects.show', $project) }}">{{ $project->bpin }}</a><p class="mt-1 text-sm">{{ $project->name ?? 'Nombre no reportado' }}</p><p class="mt-2 text-xs text-slate-500">No se encontró coincidencia exacta ni textual con los alias activos.</p></div><form method="post" action="{{ route('admin.investment-entity-assignments.store', $project) }}" class="grid min-w-72 gap-2">@csrf<select name="investment_entity_id" required><option value="">Seleccione una entidad</option>@foreach($entities as $entity)<option value="{{ $entity->id }}">{{ $entity->acronym ?: $entity->name }}</option>@endforeach</select><select name="role"><option value="primary">Entidad principal</option><option value="collaborator">Colaboradora</option></select><button class="btn-primary">Asignar y confirmar</button></form></div></article>
                @empty <p class="py-8 text-center text-sm text-slate-500">No hay proyectos por clasificar.</p> @endforelse
            @else
                @forelse($assignments as $assignment)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="grid gap-4 xl:grid-cols-[1fr_1fr_auto] xl:items-end"><div><a class="font-semibold text-indigo-700" href="{{ route('investments.projects.show', $assignment->project) }}">{{ $assignment->project->bpin }}</a><p class="mt-1 text-sm">{{ $assignment->project->name ?? 'Nombre no reportado' }}</p><p class="mt-2 text-xs text-slate-500">Evidencia: {{ data_get($assignment->evidence, 'field', 'N/D') }} · {{ data_get($assignment->evidence, 'matched_value', 'N/D') }}</p></div><div><p class="text-sm font-semibold">{{ $assignment->entity->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $assignment->method }} · confianza {{ $assignment->confidence === null ? 'N/D' : number_format((float)$assignment->confidence * 100, 0).'%' }} · {{ $assignment->role }}</p>@if($assignment->reviewer)<p class="mt-1 text-xs text-slate-500">Revisó {{ $assignment->reviewer->name }}</p>@endif</div>
                            @if($status === 'suggested')<form method="post" action="{{ route('admin.investment-entity-assignments.update', $assignment) }}" class="grid min-w-72 gap-2">@csrf @method('PATCH')<select name="investment_entity_id">@foreach($entities as $entity)<option value="{{ $entity->id }}" @selected($entity->id === $assignment->investment_entity_id)>{{ $entity->acronym ?: $entity->name }}</option>@endforeach</select><select name="role"><option value="primary" @selected($assignment->role === 'primary')>Entidad principal</option><option value="collaborator" @selected($assignment->role === 'collaborator')>Colaboradora</option></select><div class="flex gap-2"><button class="btn-primary grow" name="decision" value="confirm">Confirmar</button><button class="btn-danger" name="decision" value="reject">Rechazar</button></div></form>@endif
                        </div>
                    </article>
                @empty <p class="py-8 text-center text-sm text-slate-500">No hay clasificaciones en este estado.</p> @endforelse
            @endif
        </div>
        <div class="mt-5">{{ $status === 'unclassified' ? $unclassifiedProjects->links() : $assignments->links() }}</div>
    </section>
</div>
@endsection
